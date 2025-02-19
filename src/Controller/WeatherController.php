<?php

namespace App\Controller;

use App\Entity\Geo;
use App\Repository\GeoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Contracts\Translation\TranslatorInterface;

use function file_get_contents;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class WeatherController extends AbstractController
{
    public function __construct(
        private readonly GeoRepository $geoRepository,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/api/meteo', name: 'weather_city_default', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $user = $this->getUser();
        if ($user === null) {
            return new JsonResponse(
                'Utilisateur non trouvé',
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->getWeatherFromCity($user->getCity());
    }

    #[Route('/api/meteo/{city}', name: 'weather_city_custom', methods: ['GET'])]
    public function show2(string $city): JsonResponse
    {
        return $this->getWeatherFromCity($city);
    }

    protected function getWeatherFromCity(string $city): JsonResponse
    {
        $geo = $this->getCoordinatesFromCity($city);
        if ($geo === null) {
            return new JsonResponse(
                sprintf('Ville non trouvée : %s', $city),
                Response::HTTP_NOT_FOUND,
            );
        }

        return new JsonResponse($this->getWeatherFromGeo($geo));
    }

    protected function getCoordinatesFromCity(string $city): ?Geo
    {
        // vérifier si les infos ne sont pas déjà en cache
        $geo = $this->geoRepository->findOneBy(['name' => $city]);
        if ($geo === null) {
            try {
                $geo_result = json_decode(
                    file_get_contents(
                        $this->translator->trans(
                            $_ENV['GEO_COORDINATES_URL'],
                            [
                                '{API_KEY}' => $_ENV['OPENWEATHERMAP_API_KEY'],
                                '{CITY}' => $city,
                            ],
                        ),
                    ),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                )[0];
                // mettre en cache les infos
                $geo = new Geo();
                $geo->setCountryCode($geo_result['country']);
                $geo->setName($geo_result['name']);
                $geo->setLatitude($geo_result['lat']);
                $geo->setLongitude($geo_result['lon']);
                $this->entityManager->persist($geo);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                return null;
            }
        }

        return $geo;
    }

    protected function getWeatherFromGeo(Geo $geo): ?array
    {
        try {
            return [
                'city' => $geo->getName(),
                'weather' => json_decode(
                    file_get_contents(
                        $this->translator->trans(
                            $_ENV['CURRENT_WEATHER_URL'],
                            [
                                '{API_KEY}' => $_ENV['OPENWEATHERMAP_API_KEY'],
                                '{LATITUDE}' => $geo->getLatitude(),
                                '{LONGITUDE}' => $geo->getLongitude(),
                                '{LANGUAGE_CODE}' => 'FR',
                            ],
                        ),
                    ),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                ),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}

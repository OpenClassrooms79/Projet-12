<?php

namespace App\Controller;

use App\Entity\Geo;
use App\Repository\GeoRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Contracts\Translation\TranslatorInterface;

use function file_get_contents;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class WeatherController extends AbstractController
{
    public function __construct(private GeoRepository $geoRepository, private TranslatorInterface $translator, private EntityManagerInterface $entityManager) {}

    #[Route('/api/meteo', name: 'app_weather_city_default', methods: ['GET'])]
    public function show(UserRepository $userRepository): Response
    {
        // TODO récupérer la ville de l'utilisateur courant
        $user = $userRepository->findOneBy(['id' => 15]);
        if ($user === null) {
            return $this->json([
                'code' => 404,
                'message' => 'Utilisateur non trouvé',
            ]);
        }

        return $this->getWeatherFromCity($user->getCity());
    }

    #[Route('/api/meteo/{city}', name: 'app_weather_city', methods: ['GET'])]
    public function show2(string $city): Response
    {
        return $this->getWeatherFromCity($city);
    }

    protected function getWeatherFromCity(string $city): Response
    {
        $geo = $this->getCoordinatesFromCity($city);
        if ($geo === null) {
            return $this->json([
                'code' => 404,
                'message' => 'Ville non trouvée',
            ]);
        }

        $weather_result = $this->getWeatherFromGeo($geo);
        return $this->json([
            'code' => 200,
            'message' => $weather_result,
        ]);
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
            return json_decode(
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
            );
        } catch (\Exception $e) {
            return null;
        }
    }
}

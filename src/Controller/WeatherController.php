<?php

namespace App\Controller;

use App\Entity\Geo;
use App\Entity\Weather;
use App\Repository\GeoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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

        /* TODO vérifier si les données en cache sont suffisamment récentes (utiliser Weather::CACHE_DURATION)
         * TODO si trop anciennes, appeler l'API.
         */

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

                if ($geo_result['local_names']['fr']) {
                    // si le nom en français existe, on utilise celui-ci
                    $geo->setName($geo_result['local_names']['fr']);
                } else {
                    // sinon on utilise le nom par défaut
                    $geo->setName($geo_result['name']);
                }

                $geo->setLatitude($geo_result['lat']);
                $geo->setLongitude($geo_result['lon']);
                $this->entityManager->persist($geo);
                $this->entityManager->flush();
            } catch (Exception) {
                return null;
            }
        }

        return $geo;
    }

    protected function getWeatherFromGeo(Geo $geo): ?array
    {
        // récupération dans la base de la météo en cache pour ces coordonnées
        $weatherCollection = $geo->getWeather();

        if ($weatherCollection->isEmpty()) {
            try {
                $weather_data = json_decode(
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

                $weather = new Weather();
                $weather->setGeo($geo);
                $weather->setDate(\DateTime::createFromFormat('U', $weather_data['dt']));
                $weather->setDescription($weather_data['weather'][0]['description']);

                $this->entityManager->persist($weather);
                $this->entityManager->flush();

                $from = 'API';
            } catch (Exception) {
                return null;
            }
        } else {
            $weather = $weatherCollection->first();
            $from = 'cache';
        }

        return [
            'city' => $geo->getName(),
            'weather' => $weather->getDescription(),
            'date' => $weather->getDate()->format('d/m/Y H:i:s'),
            'from' => $from,
        ];
    }
}

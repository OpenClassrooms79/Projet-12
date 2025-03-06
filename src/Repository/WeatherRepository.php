<?php

namespace App\Repository;

use App\Entity\Geo;
use App\Entity\Weather;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Contracts\Translation\TranslatorInterface;

use function file_get_contents;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * @extends ServiceEntityRepository<Weather>
 */
class WeatherRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly TranslatorInterface $translator,
        private readonly GeoRepository $geoRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($registry, Weather::class);
    }

    public function getWeatherFromCity(string $city): JsonResponse
    {
        $geo = $this->geoRepository->getCoordinatesFromCity($city);
        if ($geo === null) {
            return new JsonResponse(
                sprintf('Ville non trouvée : %s', $city),
                Response::HTTP_NOT_FOUND,
            );
        }

        return new JsonResponse($this->getWeatherFromGeo($geo));
    }

    protected function getWeatherFromGeo(Geo $geo): ?array
    {
        // récupération dans la base de la météo en cache pour ces coordonnées
        $weatherCollection = $geo->getWeather();

        if ($weatherCollection->isEmpty()) {
            $weather = $this->getWeatherFromAPI($geo);
            $from = 'API';
        } else {
            $weather = $weatherCollection->first();

            // Si les données en cache sont trop anciennes, appeler l'API
            $diff = (new DateTime())->getTimestamp() - $weather->getDate()->getTimestamp();
            if ($diff > $_ENV['CACHE_DURATION']) {
                // effacer anciennes données
                $this->entityManager->remove($weather);
                $this->entityManager->flush();

                // récupérer les nouvelles données
                $weather = $this->getWeatherFromAPI($geo);
                $from = 'API';
            } else {
                $from = 'cache';
            }
        }

        return [
            'city' => $geo->getName(),
            'weather' => $weather->getDescription(),
            'date' => $weather->getDate()->format('d/m/Y H:i:s'),
            'from' => $from,
        ];
    }

    public function getWeatherFromAPI(Geo $geo): ?Weather
    {
        $weather_data = json_decode(
            file_get_contents(
                $this->translator->trans(
                    $_ENV['CURRENT_WEATHER_URL'],
                    [
                        '{API_KEY}' => urlencode($_ENV['OPENWEATHERMAP_API_KEY']),
                        '{LATITUDE}' => urlencode($geo->getLatitude()),
                        '{LONGITUDE}' => urlencode($geo->getLongitude()),
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
        $weather->setDate(new DateTime('now'));
        $weather->setDescription($weather_data['weather'][0]['description']);

        $this->entityManager->persist($weather);
        $this->entityManager->flush();

        return $weather;
    }
}

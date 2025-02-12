<?php

namespace App\Controller;

use App\Entity\Geo;
use App\Repository\GeoRepository;
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
    #[Route('/api/meteo/{city}', name: 'app_weather_city', methods: ['GET'])]
    public function index(string $city, GeoRepository $geoRepository, TranslatorInterface $translator, EntityManagerInterface $entityManager): Response
    {
        // vérifier si les infos ne sont pas déjà en cache
        $geo = $geoRepository->findOneBy(['name' => $city]);
        if ($geo !== null) {
            $geo_result = [
                'lat' => $geo->getLatitude(),
                'lon' => $geo->getLongitude(),
            ];
        } else {
            try {
                $geo_result = json_decode(
                    file_get_contents(
                        $translator->trans(
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
            } catch (\Exception $e) {
                return $this->json([
                    'code' => 500,
                    'message' => 'Erreur 1 du serveur',
                ], 500);
            }

            // mettre en cache les infos
            $geo = new Geo();
            $geo->setCountryCode($geo_result['country']);
            $geo->setName($geo_result['name']);
            $geo->setLatitude($geo_result['lat']);
            $geo->setLongitude($geo_result['lon']);
            $entityManager->persist($geo);
            $entityManager->flush();
        }

        // récupération de la météo
        try {
            $weather_result = json_decode(
                file_get_contents(
                    $translator->trans(
                        $_ENV['CURRENT_WEATHER_URL'],
                        [
                            '{API_KEY}' => $_ENV['OPENWEATHERMAP_API_KEY'],
                            '{LATITUDE}' => $geo_result['lat'],
                            '{LONGITUDE}' => $geo_result['lon'],
                            '{LANGUAGE_CODE}' => 'FR',
                        ],
                    ),
                ),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => $e->getMessage(),
            ], 500);
        }

        return $this->json([
            'code' => 200,
            'message' => $weather_result,
        ]);
    }
}

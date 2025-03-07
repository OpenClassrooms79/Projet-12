<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\WeatherRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WeatherController extends AbstractController
{
    public function __construct(
        private readonly WeatherRepository $weatherRepository,
    ) {}

    /**
     * renvoie les données météo de la ville de l'utilisateur authentifié
     *
     * @return JsonResponse
     */
    #[Route('/api/meteo', name: 'weather_city_default', methods: [Request::METHOD_GET])]
    public function show(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user === null) {
            return new JsonResponse(
                'Utilisateur non trouvé',
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->weatherRepository->getWeatherFromCity($user->getCity());
    }

    /**
     * renvoie les données météo de la ville donnée
     *
     * @param string $city
     * @return JsonResponse
     */
    #[Route('/api/meteo/{city}', name: 'weather_city_custom', methods: [Request::METHOD_GET])]
    public function show2(string $city): JsonResponse
    {
        return $this->weatherRepository->getWeatherFromCity($city);
    }
}

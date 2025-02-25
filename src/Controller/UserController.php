<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    // crée un nouvel utilisateur
    #[Route('/api/user', name: 'user_add', methods: ['POST'])]
    public function create(UserPasswordHasherInterface $userPasswordHasher, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['login'], $data['password'], $data['city'])) {
            return new JsonResponse(
                [
                    'message' => 'Pour creer un nouvel utilisateur, veuillez fournir un login, un mot de passe et une ville.',
                    'paramètres' => [
                        'login' => 'string',
                        'password' => 'string',
                        'city' => 'string',
                    ],
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $login = mb_trim($data['login']);
        $password = mb_trim($data['password']);
        $city = mb_trim($data['city']);

        if ($login === '' || $password === '' || $city === '') {
            return new JsonResponse(
                [
                    'message' => 'Veuillez fournir un login, un mot de passe et une ville.',
                    'paramètres' => [
                        'login' => 'string',
                        'password' => 'string',
                        'city' => 'string',
                    ],
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }


        $user = new User();
        $user
            ->setLogin($data['login'])
            ->setPassword($userPasswordHasher->hashPassword($user, $data['password']))
            ->setCity($data['city'])
            ->setRoles(['ROLE_USER']);
        $this->entityManager->persist($user);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return new JsonResponse(
                'Utilisateur deja existant : ' . $data['login'],
                Response::HTTP_CONFLICT,
            );
        }

        return new JsonResponse(
            [
                'id' => $user->getId(),
                'message' => sprintf('Utilisateur créé : %s', $data['login']),
            ],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/api/user/{id}/{city}', name: 'user_update', requirements: ['month' => Requirement::POSITIVE_INT], methods: ['PUT'])]
    public function update(int $id, string $city): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if ($user === null) {
            return new JsonResponse(
                [
                    'id' => $id,
                    'message' => 'Utilisateur non trouvé',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $city = mb_trim($city);
        if ($city === '') {
            return new JsonResponse(
                [
                    'id' => $id,
                    'message' => 'Veuillez fournir une ville',
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $user->setCity($city);
        $this->entityManager->flush();

        return new JsonResponse(
            [
                'id' => $id,
                'message' => 'Utilisateur mis à jour',
            ],
        );
    }

    #[Route('/api/user/{id}', name: 'user_delete', requirements: ['month' => Requirement::POSITIVE_INT], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if ($user === null) {
            return new JsonResponse(
                [
                    'id' => $id,
                    'message' => 'Utilisateur non trouvé',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse(
            [
                'id' => $id,
                'message' => 'Utilisateur supprimé',
            ],
        );
    }
}

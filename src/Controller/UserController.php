<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    #[Route('/api/user/{login}/{password}/{city}', name: 'user_add', methods: ['POST'])]
    public function create(string $login, string $password, string $city, UserPasswordHasherInterface $userPasswordHasher): JsonResponse
    {
        $user = new User();
        $user
            ->setLogin($login)
            ->setPassword($userPasswordHasher->hashPassword($user, $password))
            ->setCity($city)
            ->setRoles(['ROLE_USER']);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(
            [
                'id' => $user->getId(),
                'message' => sprintf('Utilisateur créé : %s', $login),
            ],
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

<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * crée un nouvel utilisateur
     *
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @param Request $request
     * @return JsonResponse
     * @throws JsonException
     */
    #[Route('/api/user', name: 'user_add', methods: [Request::METHOD_POST])]
    public function create(UserPasswordHasherInterface $userPasswordHasher, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['login'], $data['password'], $data['city'])) {
            return new JsonResponse(
                [
                    'errors' => [
                        'status' => Response::HTTP_BAD_REQUEST,
                        'code' => 'invalid_request',
                        'source' => ['parameter' => ['login', 'password', 'city']],
                        'title' => 'Paramètre manquant',
                        'detail' => "Au moins l'un des paramètres est manquant",
                    ],
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $login = mb_trim($data['login']);
        $password = $data['password'];
        $city = mb_trim($data['city']);

        if ($login === '' || $password === '' || $city === '') {
            return new JsonResponse(
                [
                    'errors' => [
                        'status' => Response::HTTP_BAD_REQUEST,
                        'code' => 'invalid_request',
                        'source' => ['parameter' => ['login', 'password', 'city']],
                        'title' => 'Valeur incorrecte',
                        'detail' => "Aucun des paramètres ne peut avoir une valeur vide",
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
                'Utilisateur déjà existant : ' . $data['login'],
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

    /**
     * mise à jour d'un utilisateur existant
     *
     * @param Request $request
     * @return JsonResponse
     * @throws JsonException
     */
    #[Route('/api/user/{id}', name: 'user_update', requirements: ['month' => Requirement::POSITIVE_INT], methods: [Request::METHOD_PUT])]
    public function update(int $id, Request $request): JsonResponse
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

        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['city'])) {
            return new JsonResponse(
                [
                    'errors' => [
                        'status' => Response::HTTP_BAD_REQUEST,
                        'code' => 'invalid_request',
                        'source' => ['parameter' => ['city']],
                        'title' => 'Paramètre manquant',
                        'detail' => "Au moins l'un des paramètres est manquant",
                    ],
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }
        $city = $data['city'];

        $city = mb_trim($city);
        if ($city === '') {
            return new JsonResponse(
                [
                    'errors' => [
                        'status' => Response::HTTP_BAD_REQUEST,
                        'code' => 'invalid_request',
                        'source' => ['parameter' => 'city'],
                        'title' => 'Valeur incorrecte',
                        'detail' => "Le paramètre ne peut avoir une valeur vide",
                    ],
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

    /**
     * suppression d'un utilisateur
     *
     * @param int $id
     * @return JsonResponse
     */
    #[Route('/api/user/{id}', name: 'user_delete', requirements: ['month' => Requirement::POSITIVE_INT], methods: [Request::METHOD_DELETE])]
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

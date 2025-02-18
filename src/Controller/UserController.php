<?php

namespace App\Controller;

use App\Entity\Advice;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

use function array_filter;
use function count;
use function explode;
use function sprintf;

final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    // ajoute un nouveau conseil pour le(s) mois(s) donné(s) (séparés par une virgule)
    #[Route('/api/user/{login}/{password}/{city}', name: 'advice_add', methods: ['POST'])]
    public function create(string $months, string $detail): Response
    {
        $months_array = explode(',', $months);
        $months_array = array_filter($months_array, static function ($num) {
            return $num >= 1 && $num <= 12;
        });

        foreach ($months_array as $num) {
            $month = $this->monthRepository->getMonthByNum((int) $num);
            if ($month !== null) {
                $advice = new Advice();
                $advice
                    ->addMonth($month)
                    ->setDetail($detail);
                $this->entityManager->persist($advice);
            }
        }
        $this->entityManager->flush();

        return $this->json([
            'code' => 200,
            'message' => sprintf('Conseil créé, mois associés : %d', count($months_array)),
        ]);
    }

    #[Route('/api/user/{id}/{city}', name: 'user_update', requirements: ['month' => Requirement::POSITIVE_INT], methods: ['PUT'])]
    public function update(int $id, string $city): Response
    {
        $user = $this->userRepository->find($id);

        if ($user === null) {
            return $this->json([
                'code' => 404,
                'id' => $id,
                'message' => 'Utilisateur non trouvé',
            ]);
        }

        $user->setCity($city);
        $this->entityManager->flush();

        return $this->json([
            'code' => 200,
            'id' => $id,
            'message' => 'Utilisateur mis à jour',
        ]);
    }

    #[Route('/api/user/{id}', name: 'user_delete', requirements: ['month' => Requirement::POSITIVE_INT], methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $user = $this->userRepository->find($id);

        if ($user === null) {
            return $this->json([
                'code' => 404,
                'id' => $id,
                'message' => 'Utilisateur non trouvé',
            ]);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return $this->json([
            'code' => 200,
            'id' => $id,
            'message' => 'Utilisateur supprimé',
        ]);
    }
}

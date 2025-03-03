<?php

namespace App\Controller;

use App\Entity\Advice;
use App\Repository\AdviceRepository;
use App\Repository\MonthRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

use function array_filter;
use function count;
use function date;
use function explode;

final class AdviceController extends AbstractController
{
    public function __construct(
        private readonly AdviceRepository $adviceRepository,
        private readonly MonthRepository $monthRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    // liste tous les conseils du mois en cours
    #[Route('/api/conseil', name: 'advice_current_month', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse($this->adviceRepository->getAdvicesByMonth((int) date('n')));
    }

    // liste tous les conseils du mois donné
    #[Route('/api/conseil/{month}', name: 'advice_specific_month', requirements: ['month' => Requirement::POSITIVE_INT], methods: ['GET'])]
    public function index2(int $month): JsonResponse
    {
        if ($month < 1 || $month > 12) {
            return new JsonResponse(
                'Le numéro du mois doit être entre 1 et 12',
                Response::HTTP_BAD_REQUEST,
            );
        }

        return new JsonResponse($this->adviceRepository->getAdvicesByMonth($month));
    }

    // ajoute un nouveau conseil pour le(s) mois(s) donné(s) (séparés par un tiret)
    #[Route('/api/conseil/{months}/{detail}', name: 'advice_add', methods: ['POST'])]
    public function create(string $months, string $detail): JsonResponse
    {
        $months_array = explode('-', $months);
        $months_array = array_filter($months_array, static function ($num) {
            return $num >= 1 && $num <= 12;
        });

        if (count($months_array) === 0) {
            return new JsonResponse(
                [
                    'message' => 'Veuillez fournir au moins un numéro de mois valide.',
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $detail = mb_trim($detail);
        if ($detail === '') {
            return new JsonResponse(
                [
                    'message' => 'Veuillez fournir un conseil.',
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $advice = new Advice();
        $advice->setDetail($detail);
        $months_names = [];
        foreach ($months_array as $num) {
            $month = $this->monthRepository->getMonthByNum((int) $num);
            if ($month !== null) {
                $advice->addMonth($month);
                $months_names[] = $month->getName();
            }
        }
        $this->entityManager->persist($advice);
        $this->entityManager->flush();

        return new JsonResponse(
            [
                'months' => $months_names,
                'id' => $advice->getId(),
                'message' => 'Conseil créé',
            ],
            Response::HTTP_CREATED,
        );
    }

    // met à jour la liste des mois du conseil $id, et éventuellement le détail
    #[Route('/api/conseil/{id}/{months}', name: 'advice_update', methods: ['PUT'])]
    #[Route('/api/conseil/{id}/{months}/{detail}', name: 'advice_update2', methods: ['PUT'])]
    public function update(int $id, string $months, ?string $detail = null): JsonResponse
    {
        $advice = $this->adviceRepository->find($id);
        if ($advice === null) {
            return new JsonResponse(
                [
                    'id' => $id,
                    'message' => 'Conseil non trouvé',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $months_array = explode(',', $months);
        $months_array = array_filter($months_array, static function ($month) {
            return $month >= 1 && $month <= 12;
        });

        if (count($months_array) === 0) {
            return new JsonResponse(
                [
                    'message' => 'Veuillez fournir au moins un numéro de mois valide.',
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        // suppression de tous les mois auxquels le conseil est actuellement associé
        $advice->getMonths()->clear();

        // ajout des nouveaux mois au conseil
        $months_names = [];
        foreach ($months_array as $num) {
            $month = $this->monthRepository->find($num);
            if ($month !== null) {
                $advice->addMonth($month);
                $months_names[] = $month->getName();
            }
        }

        // mise à jour du détail (si fourni dans la requête)

        if ($detail !== null) {
            $detail = mb_trim($detail);
            if ($detail !== '' && $detail !== $advice->getDetail()) {
                $advice->setDetail($detail);
            } else {
                return new JsonResponse(
                    [
                        'message' => 'Si le détail est fourni, ce dernier ne peut pas être vide.',
                    ],
                    Response::HTTP_BAD_REQUEST,
                );
            }
        }

        $this->entityManager->flush();

        return new JsonResponse(
            [
                'conseil' => [
                    'id' => $advice->getId(),
                    'detail' => $advice->getDetail(),
                ],
                'months' => $months_names,
                'message' => 'Conseil mis à jour',
            ],
        );
    }

    #[Route('/api/conseil/{id}', name: 'advice_delete', requirements: ['id' => Requirement::POSITIVE_INT], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $advice = $this->adviceRepository->find($id);

        if ($advice === null) {
            return new JsonResponse(
                [
                    'id' => $id,
                    'message' => 'Conseil non trouvé',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $this->entityManager->remove($advice);
        $this->entityManager->flush();

        return new JsonResponse(
            [
                'id' => $id,
                'message' => 'Conseil supprimé',
            ],
        );
    }
}

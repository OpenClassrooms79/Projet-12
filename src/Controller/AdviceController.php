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
use function sprintf;

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
        return new JsonResponse($this->getAdvicesByMonth((int) date('n')));
    }

    // liste tous les conseils du mois donné
    #[Route('/api/conseil/{month}', name: 'advice_specific_month', requirements: ['month' => Requirement::POSITIVE_INT], methods: ['GET'])]
    public function index2(int $month): JsonResponse
    {
        if ($month < 1 || $month > 12) {
            return new JsonResponse(
                'Le numéro du mois doit être entre 1 et 12',
                Response::HTTP_NOT_FOUND,
            );
        }

        return new JsonResponse($this->getAdvicesByMonth($month));
    }

    protected function getAdvicesByMonth(int $month): array
    {
        return $this->adviceRepository
            ->createQueryBuilder('a')
            ->select('a.id', 'a.detail', 'm.name')
            ->innerJoin('a.months', 'm')
            ->where('m.num = :num')
            ->setParameter('num', $month)
            ->getQuery()
            ->getResult();
    }

    // ajoute un nouveau conseil pour le(s) mois(s) donné(s) (séparés par une virgule)
    #[Route('/api/conseil/{months}/{detail}', name: 'advice_add', methods: ['POST'])]
    public function create(string $months, string $detail): JsonResponse
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

        return new JsonResponse(
            sprintf('Conseil créé, mois associés : %d', count($months_array)),
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

        // suppression de tous les mois auxquels le conseil est actuellement associé
        $advice->getMonths()->clear();

        // ajout des nouveaux mois au conseil
        $count = 0;
        foreach ($months_array as $num) {
            $month = $this->monthRepository->find($num);
            if ($month !== null) {
                $advice->addMonth($month);
                $count++;
            }
        }

        // mise à jour du détail (si fourni dans la requête)
        if ($detail !== null) {
            $advice->setDetail($detail);
        }

        $this->entityManager->flush();

        return new JsonResponse(
            [
                'id' => $id,
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

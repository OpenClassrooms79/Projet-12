<?php

namespace App\Controller;

use App\Entity\Advice;
use App\Entity\Month;
use App\Repository\AdviceRepository;
use App\Repository\MonthRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    // liste tous les conseils du mois en cours
    #[Route('/api/conseil', name: 'advice_current_month', methods: ['GET'])]
    public function index(AdviceRepository $adviceRepository): Response
    {
        return $this->json($this->getAdvicesByMonth((int) date('n'), $adviceRepository));
    }

    // liste tous les conseils du mois donné
    #[Route('/api/conseil/{month}', name: 'advice_specific_month', requirements: ['month' => Requirement::POSITIVE_INT], methods: ['GET'])]
    public function index2(int $month, AdviceRepository $adviceRepository): Response
    {
        if ($month < 1 || $month > 12) {
            return $this->json([
                'code' => 404,
                'message' => 'Le numéro du mois doit être entre 1 et 12',
            ], 404);
        }

        return $this->json($this->getAdvicesByMonth($month, $adviceRepository));
    }

    protected function getAdvicesByMonth(int $month, AdviceRepository $adviceRepository): array
    {
        return $adviceRepository
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
    public function create(string $months, string $detail, EntityManagerInterface $entityManager, MonthRepository $monthRepository): Response
    {
        $months_array = explode(',', $months);
        $months_array = array_filter($months_array, static function ($num) {
            return $num >= 1 && $num <= 12;
        });

        foreach ($months_array as $num) {
            $month = $monthRepository->getMonthByNum((int) $num);
            $advice = new Advice();
            $advice
                ->addMonth($month)
                ->setDetail($detail);
            $entityManager->persist($advice);
        }
        $entityManager->flush();

        return $this->json([
            'code' => 200,
            'message' => sprintf('Conseils ajoutés : %d', count($months_array)),
        ]);
    }

    // met à jour la liste des mois du conseil $id, et éventuellement le détail
    #[Route('/api/conseil/{id}/{months}', name: 'advice_update', methods: ['PUT'])]
    #[Route('/api/conseil/{id}/{months}/{detail}', name: 'advice_update2', methods: ['PUT'])]
    public function update(EntityManagerInterface $entityManager, AdviceRepository $adviceRepository, MonthRepository $monthRepository, int $id, string $months, ?string $detail = null): Response
    {
        $months_array = explode(',', $months);
        $months_array = array_filter($months_array, static function ($month) {
            return $month >= 1 && $month <= 12;
        });

        $advice = $adviceRepository->find($id);
        if ($advice === null) {
            return $this->json([
                'code' => 404,
                'message' => 'Conseil non trouvé',
            ]);
        }

        // suppression de tous les mois auxquels le conseil est actuellement associé
        $advice->getMonths()->clear();

        // ajout des nouveaux mois au conseil
        $count = 0;
        foreach ($months_array as $num) {
            $month = $monthRepository->find($num);
            if ($month !== null) {
                $advice->addMonth($month);
                $count++;
            }
        }

        // mise à jour du détail (si fourni dans la requête)
        if ($detail !== null) {
            $advice->setDetail($detail);
        }

        $entityManager->flush();

        return $this->json([
            'code' => 200,
            'message' => sprintf('Conseils ajoutés : %d', $count),
        ]);
    }
}

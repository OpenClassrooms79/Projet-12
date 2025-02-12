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
    #[Route('/api/conseil', name: 'advice_index', methods: ['GET'])]
    public function index(AdviceRepository $adviceRepository, MonthRepository $monthRepository): Response
    {
        $currentMonth = $monthRepository->findOneBy(['num' => (int) date('n')]);

        $advices = $adviceRepository
            ->createQueryBuilder('a')
            ->innerJoin('a.months', 'm')
            ->where('m = :month')
            ->setParameter('month', $currentMonth)
            ->getQuery()
            ->getResult();

        return $this->json([$advices]);
    }

    // affiche tous les conseils du mois donné
    #[Route('/api/conseil/{month}', name: 'advice_show', requirements: ['month' => Requirement::POSITIVE_INT], methods: ['GET'])]
    public function show(int $month, AdviceRepository $adviceRepository, MonthRepository $monthRepository): Response
    {
        if ($month < 1 || $month > 12) {
            return $this->json([
                'code' => 404,
                'message' => 'Le numéro du mois doit être entre 1 et 12',
            ], 404);
        }

        $currentMonth = $monthRepository->findOneBy(['num' => $month]);

        $advices = $adviceRepository
            ->createQueryBuilder('a')
            ->innerJoin('a.months', 'm')
            ->where('m = :month')
            ->setParameter('month', $currentMonth)
            ->getQuery()
            ->getResult();

        return $this->json([$advices]);
    }

    // ajoute un nouveau conseil pour le(s) mois(s) donné(s) (séparés par une virgule)
    #[Route('/api/conseil/{months}/{detail}', name: 'advice_add', methods: ['POST'])]
    public function create(string $months, string $detail, EntityManagerInterface $entityManager): Response
    {
        $months_array = explode(',', $months);
        $months_array = array_filter($months_array, static function ($month) {
            return $month >= 1 && $month <= 12;
        });

        foreach ($months_array as $month) {
            $advice = new Advice();
            $advice
                ->setMonth($month)
                ->setDetail($detail);
            $entityManager->persist($advice);
        }
        $entityManager->flush();

        return $this->json([
            'code' => 200,
            'message' => sprintf('Conseils ajoutés : %d', count($months_array)),
        ]);
    }

    // met à jour le conseil $id
    #[Route('/api/conseil/{id}/{months}', name: 'advice_update', methods: ['PUT'])]
    #[Route('/api/conseil/{id}/{months}/{detail}', name: 'advice_update2', methods: ['PUT'])]
    public function update(EntityManagerInterface $entityManager, AdviceRepository $adviceRepository, string $months, ?string $detail = null): Response
    {
        $months_array = explode(',', $months);
        $months_array = array_filter($months_array, static function ($month) {
            return $month >= 1 && $month <= 12;
        });

        $queryBuilder = $entityManager->getRepository(Advice::class)->createQueryBuilder('a');
        if ($detail === null) {
            // mise à jour uniquement de la liste des mois
            $queryBuilder
                ->update()
                ->set('a.months', ':months')
                ->where('a.id = :id')
                ->setParameter('months', $months_array)
                ->setParameter('id', 1)
                ->getQuery()
                ->execute();
        } else {
            // mise à jour de la liste des mois et du detail
            $queryBuilder
                ->update()
                ->set('a.months', ':months')
                ->set('a.detail', ':detail')
                ->where('a.id = :id')
                ->setParameter('months', $months_array)
                ->setParameter('detail', $detail)
                ->setParameter('id', 1)
                ->getQuery()
                ->execute();
        }

        return $this->json([
            'code' => 200,
            'message' => sprintf('Conseils ajoutés : %d', count($months_array)),
        ]);
    }
}

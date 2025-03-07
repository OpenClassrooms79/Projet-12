<?php

namespace App\Repository;

use App\Entity\Advice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Advice>
 */
class AdviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Advice::class);
    }

    /**
     * @param int $month
     * @return array tableau des conseils associés au mois numéro $month (1 à 12)
     */
    public function getAdvicesByMonth(int $month): array
    {
        return $this
            ->createQueryBuilder('a')
            ->select('a.id', 'a.detail', 'm.name')
            ->innerJoin('a.months', 'm')
            ->where('m.num = :num')
            ->setParameter('num', $month)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Advice $advice
     * @return array tableau des mois associés au conseil $advice
     */
    public function getMonthNames(Advice $advice): array
    {
        return array_column(
            $this
                ->createQueryBuilder('a')
                ->select('m.name')
                ->innerJoin('a.months', 'm')
                ->where('a.id = :id')
                ->setParameter('id', $advice->getId())
                ->getQuery()
                ->getResult(),
            'name',
        );
    }
}

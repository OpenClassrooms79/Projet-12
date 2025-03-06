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
}

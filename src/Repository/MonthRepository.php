<?php

namespace App\Repository;

use App\Entity\Month;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Month>
 */
class MonthRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Month::class);
    }

    /**
     * retourne une instance de Month qui a comme num $num
     *
     * @param int $num
     * @return Month|null
     */
    public function getMonthByNum(int $num): ?Month
    {
        return $this->findOneBy(['num' => $num]);
    }
}

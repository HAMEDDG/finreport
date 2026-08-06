<?php

namespace App\Repository;

use App\Entity\Report;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Report>
 */
class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    /**
     * @return Report[]
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.dateGeneration', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Report[]
     */
    public function findRecent(int $limite = 5): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.dateGeneration', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}

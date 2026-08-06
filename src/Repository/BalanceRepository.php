<?php

namespace App\Repository;

use App\Entity\Balance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Balance>
 */
class BalanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Balance::class);
    }

    /**
     * @return Balance[]
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.dateImportation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Balance[]
     */
    public function findRecent(int $limite = 5): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.dateImportation', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, int> Nombre de balances par statut (reussi, erreur, en_attente)
     */
    public function countParStatut(): array
    {
        $lignes = $this->createQueryBuilder('b')
            ->select('b.statut, COUNT(b.id) as total')
            ->groupBy('b.statut')
            ->getQuery()
            ->getResult();

        $resultat = ['reussi' => 0, 'erreur' => 0, 'en_attente' => 0];
        foreach ($lignes as $ligne) {
            $resultat[$ligne['statut']] = (int) $ligne['total'];
        }

        return $resultat;
    }
}
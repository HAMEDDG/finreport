<?php

namespace App\Repository;

use App\Entity\AppSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppSettings>
 */
class AppSettingsRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($registry, AppSettings::class);
    }

    /**
     * Il n'existe qu'une seule ligne de paramètres. On la crée avec des
     * valeurs par défaut si elle n'existe pas encore — l'application ayant
     * été conçue spécifiquement pour Ivoire Moto, ce nom est préconfiguré
     * dès la première utilisation plutôt que de laisser le champ vide.
     */
    public function getSettings(): AppSettings
    {
        $settings = $this->findOneBy([]);

        if ($settings === null) {
            $settings = new AppSettings();
            $settings->setNomEntreprise('Ivoire Moto');
            $this->entityManager->persist($settings);
            $this->entityManager->flush();
        }

        return $settings;
    }
}

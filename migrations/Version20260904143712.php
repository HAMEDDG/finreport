<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remplace les colonnes compte_charges / compte_produits (T-compte à plat)
 * par une unique colonne compte_resultat : le Compte de Résultat au format
 * "liste" avec soldes intermédiaires de gestion (SYSCOHADA système normal).
 */
final class Version20260904143712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remplace compte_charges/compte_produits par compte_resultat (format liste avec SIG)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report ADD compte_resultat JSON NOT NULL');
        $this->addSql('ALTER TABLE report DROP compte_charges, DROP compte_produits');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report ADD compte_charges JSON NOT NULL, ADD compte_produits JSON NOT NULL');
        $this->addSql('ALTER TABLE report DROP compte_resultat');
    }
}

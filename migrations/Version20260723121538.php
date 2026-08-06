<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723121538 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE report (id INT AUTO_INCREMENT NOT NULL, entreprise VARCHAR(180) NOT NULL, exercice VARCHAR(4) NOT NULL, type VARCHAR(100) NOT NULL, statut VARCHAR(20) NOT NULL, date_generation DATETIME NOT NULL, bilan_actif JSON NOT NULL, bilan_passif JSON NOT NULL, compte_charges JSON NOT NULL, compte_produits JSON NOT NULL, utilisateur_id INT NOT NULL, balance_id INT DEFAULT NULL, INDEX IDX_C42F7784FB88E14F (utilisateur_id), INDEX IDX_C42F7784AE91A3DD (balance_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784AE91A3DD FOREIGN KEY (balance_id) REFERENCES balance (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report DROP FOREIGN KEY FK_C42F7784FB88E14F');
        $this->addSql('ALTER TABLE report DROP FOREIGN KEY FK_C42F7784AE91A3DD');
        $this->addSql('DROP TABLE report');
    }
}

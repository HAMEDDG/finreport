<?php

namespace App\Service;

use App\Entity\Balance;
use App\Entity\Report;
use App\Entity\User;
use App\Repository\AppSettingsRepository;
use App\Service\Accounting\AccountClassification;
use App\Service\Accounting\BalanceFileReader;
use App\Service\Accounting\SyscohadaClassifier;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Construit un rapport à partir d'une balance importée.
 *
 * Lit le fichier de balance associé, classe chaque compte selon le plan
 * SYSCOHADA révisé (App\Service\Accounting\SyscohadaClassifier) et calcule
 * les montants réels du Bilan et du Compte de Résultat — aucune donnée
 * n'est inventée : tout provient des soldes du fichier importé.
 */
class ReportGenerationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BalanceFileReader $balanceFileReader,
        private readonly SyscohadaClassifier $classifier,
        private readonly AppSettingsRepository $appSettingsRepository,
        private readonly string $balancesDirectory,
    ) {
    }

    /**
     * @throws \RuntimeException si le fichier de balance est introuvable, illisible,
     *                           mal formé, ou si les débits et crédits ne s'équilibrent pas
     */
    public function creerDepuisBalance(Balance $balance, User $utilisateur): Report
    {
        $cheminFichier = $this->balancesDirectory . '/' . $balance->getNomFichier();
        if (!is_file($cheminFichier)) {
            throw new \RuntimeException('Le fichier de balance est introuvable sur le serveur.');
        }

        $lignes = $this->balanceFileReader->lire($cheminFichier, $balance->getExtension());

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($lignes as $ligne) {
            $totalDebit += $ligne->soldeDebit;
            $totalCredit += $ligne->soldeCredit;
        }

        $toleranceEquilibre = $this->appSettingsRepository->getSettings()->getToleranceEquilibre();

        if (abs($totalDebit - $totalCredit) > $toleranceEquilibre) {
            throw new \RuntimeException(sprintf(
                "La balance n'est pas équilibrée (total débit %s, total crédit %s, %d comptes lus, tolérance %s) : impossible de générer un rapport fiable.",
                number_format($totalDebit, 2, ',', ' '),
                number_format($totalCredit, 2, ',', ' '),
                count($lignes),
                number_format($toleranceEquilibre, 2, ',', ' '),
            ));
        }

        $bilanActif = $this->structureBilanActifVide();
        $bilanPassif = $this->structureBilanPassifVide();
        $charges = $this->structureChargesVide();
        $produits = $this->structureProduitsVide();

        foreach ($lignes as $ligne) {
            $classement = $this->classifier->classifier($ligne);
            if ($classement === null) {
                continue;
            }

            match ($classement->section) {
                AccountClassification::SECTION_ACTIF => $bilanActif[$classement->rubrique] += $classement->montant,
                AccountClassification::SECTION_PASSIF => $bilanPassif[$classement->rubrique] += $classement->montant,
                AccountClassification::SECTION_CHARGES => $charges[$classement->rubrique] += $classement->montant,
                AccountClassification::SECTION_PRODUITS => $produits[$classement->rubrique] += $classement->montant,
            };
        }

        $charges['Total des charges'] = array_sum($charges);
        $produits['Total des produits'] = array_sum($produits);

        $resultatNet = $produits['Total des produits'] - $charges['Total des charges'];
        $bilanPassif["Résultat net de l'exercice"] = $resultatNet;

        $bilanActif['Total Immobilisations'] =
            $bilanActif['Immobilisations incorporelles']
            + $bilanActif['Immobilisations corporelles']
            + $bilanActif['Immobilisations financières']
            + $bilanActif['Amortissements et dépréciations (-)'];

        $bilanActif['Total Actif circulant'] =
            $bilanActif['Stocks']
            + $bilanActif['Dépréciations des stocks (-)']
            + $bilanActif['Créances et emplois assimilés'];

        $bilanActif['Total Trésorerie-Actif'] =
            $bilanActif['Titres de placement']
            + $bilanActif['Valeurs à encaisser']
            + $bilanActif['Banques, chèques postaux, caisse'];

        $bilanActif['Total Actif'] =
            $bilanActif['Total Immobilisations']
            + $bilanActif['Total Actif circulant']
            + $bilanActif['Total Trésorerie-Actif'];

        $bilanPassif['Total Capitaux propres'] =
            $bilanPassif['Capital']
            + $bilanPassif['Réserves']
            + $bilanPassif['Report à nouveau']
            + $bilanPassif["Résultat net de l'exercice"]
            + $bilanPassif["Subventions d'investissement"]
            + $bilanPassif['Provisions réglementées'];

        $bilanPassif['Total Dettes financières'] =
            $bilanPassif['Emprunts et dettes financières']
            + $bilanPassif['Provisions financières pour risques et charges'];

        $bilanPassif['Total Passif circulant'] = $bilanPassif['Dettes circulantes'];

        $bilanPassif['Total Trésorerie-Passif'] = $bilanPassif['Banques, crédits de trésorerie'];

        $bilanPassif['Total Passif'] =
            $bilanPassif['Total Capitaux propres']
            + $bilanPassif['Total Dettes financières']
            + $bilanPassif['Total Passif circulant']
            + $bilanPassif['Total Trésorerie-Passif'];

        $bilanActif = array_map(fn (float $v): float => round($v, 2), $bilanActif);
        $bilanPassif = array_map(fn (float $v): float => round($v, 2), $bilanPassif);
        $charges = array_map(fn (float $v): float => round($v, 2), $charges);
        $produits = array_map(fn (float $v): float => round($v, 2), $produits);

        $report = new Report();
        $report->setEntreprise($balance->getEntreprise());
        $report->setExercice($balance->getExercice());
        $report->setUtilisateur($utilisateur);
        $report->setBalance($balance);
        $report->setBilanActif($bilanActif);
        $report->setBilanPassif($bilanPassif);
        $report->setCompteCharges($charges);
        $report->setCompteProduits($produits);

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return $report;
    }

    private function structureBilanActifVide(): array
    {
        return [
            'Immobilisations incorporelles' => 0.0,
            'Immobilisations corporelles' => 0.0,
            'Immobilisations financières' => 0.0,
            'Amortissements et dépréciations (-)' => 0.0,
            'Total Immobilisations' => 0.0,
            'Stocks' => 0.0,
            'Dépréciations des stocks (-)' => 0.0,
            'Créances et emplois assimilés' => 0.0,
            'Total Actif circulant' => 0.0,
            'Titres de placement' => 0.0,
            'Valeurs à encaisser' => 0.0,
            'Banques, chèques postaux, caisse' => 0.0,
            'Total Trésorerie-Actif' => 0.0,
            'Total Actif' => 0.0,
        ];
    }

    private function structureBilanPassifVide(): array
    {
        return [
            'Capital' => 0.0,
            'Réserves' => 0.0,
            'Report à nouveau' => 0.0,
            'Résultat net de l\'exercice' => 0.0,
            "Subventions d'investissement" => 0.0,
            'Provisions réglementées' => 0.0,
            'Total Capitaux propres' => 0.0,
            'Emprunts et dettes financières' => 0.0,
            'Provisions financières pour risques et charges' => 0.0,
            'Total Dettes financières' => 0.0,
            'Dettes circulantes' => 0.0,
            'Total Passif circulant' => 0.0,
            'Banques, crédits de trésorerie' => 0.0,
            'Total Trésorerie-Passif' => 0.0,
            'Total Passif' => 0.0,
        ];
    }

    private function structureChargesVide(): array
    {
        return [
            'Achats de marchandises' => 0.0,
            'Variation de stocks' => 0.0,
            'Autres achats' => 0.0,
            'Transports' => 0.0,
            'Services extérieurs' => 0.0,
            'Impôts et taxes' => 0.0,
            'Autres charges' => 0.0,
            'Charges de personnel' => 0.0,
            'Dotations aux amortissements' => 0.0,
            'Charges financières' => 0.0,
            'Charges HAO' => 0.0,
            'Impôts sur le résultat' => 0.0,
            'Total des charges' => 0.0,
        ];
    }

    private function structureProduitsVide(): array
    {
        return [
            'Ventes de marchandises' => 0.0,
            'Ventes de produits fabriqués' => 0.0,
            'Travaux, services vendus' => 0.0,
            'Production immobilisée' => 0.0,
            'Production stockée' => 0.0,
            'Subventions d\'exploitation' => 0.0,
            'Autres produits' => 0.0,
            'Reprises d\'amortissements et provisions' => 0.0,
            'Produits financiers' => 0.0,
            'Produits HAO' => 0.0,
            'Total des produits' => 0.0,
        ];
    }
}

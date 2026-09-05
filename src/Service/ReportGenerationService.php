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

        $actif = $this->structureActifVide();
        $bilanPassif = $this->structureBilanPassifVide();
        $charges = $this->structureChargesVide();
        $produits = $this->structureProduitsVide();

        foreach ($lignes as $ligne) {
            $classement = $this->classifier->classifier($ligne);
            if ($classement === null) {
                continue;
            }

            match ($classement->section) {
                AccountClassification::SECTION_ACTIF => $actif[$classement->rubrique][$classement->colonne] += $classement->montant,
                AccountClassification::SECTION_PASSIF => $bilanPassif[$classement->rubrique] += $classement->montant,
                AccountClassification::SECTION_CHARGES => $charges[$classement->rubrique] += $classement->montant,
                AccountClassification::SECTION_PRODUITS => $produits[$classement->rubrique] += $classement->montant,
            };
        }

        $compteResultat = $this->construireCompteResultat($charges, $produits);
        $bilanPassif["Résultat net de l'exercice"] = $compteResultat['resultatNet'];

        $bilanActif = $this->construireBilanActif($actif);
        $bilanPassifLignes = $this->construireBilanPassif($bilanPassif);

        $report = new Report();
        $report->setEntreprise($balance->getEntreprise());
        $report->setExercice($balance->getExercice());
        $report->setUtilisateur($utilisateur);
        $report->setBalance($balance);
        $report->setBilanActif($bilanActif['lignes']);
        $report->setBilanPassif($bilanPassifLignes['lignes']);
        $report->setCompteResultat($compteResultat['lignes']);

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return $report;
    }

    /** @return array<string, array{brut: float, amortProv: float}> */
    private function structureActifVide(): array
    {
        $rubriques = [
            'Brevets, Licences', 'Logiciels', 'Terrains', 'Bâtiments', 'Installations et aménagements',
            'Matériel et Outillage', 'Matériel, Mobilier de bureau', 'Matériel informatique',
            'Immobilisations corporelles en cours', 'Avances et acomptes', 'Titres de participation',
            'Autres immobilisations financières', 'Stocks et encours', 'Fournisseurs avances versées',
            'Clients', 'autres créances', 'Titres de placement', 'Disponibilités',
        ];

        return array_fill_keys($rubriques, ['brut' => 0.0, 'amortProv' => 0.0]);
    }

    private function structureBilanPassifVide(): array
    {
        return [
            'Capital' => 0.0,
            'Actionnaires capital non appelé' => 0.0,
            "Primes d'apport, d'émission, de fusion" => 0.0,
            'Ecarts de réévaluation' => 0.0,
            'Réserves indisponibles' => 0.0,
            'Réserves libres' => 0.0,
            'Report à nouveau' => 0.0,
            'Résultat en instance d\'affectation' => 0.0,
            'Résultat net de l\'exercice' => 0.0,
            'Provisions réglementées' => 0.0,
            "Subventions d'investissement" => 0.0,
            'Emprunts et dettes financières' => 0.0,
            'Dettes de crédit-bail et contrats assimilés' => 0.0,
            'Provisions financières pour risques et charges' => 0.0,
            'Clients, avances reçues' => 0.0,
            "Fournisseurs d'exploitation" => 0.0,
            'Dettes fiscales et sociales' => 0.0,
            'Autres dettes' => 0.0,
            'Risques provisionnés' => 0.0,
            "Banques, crédits d'escompte" => 0.0,
            'Banques, crédits de trésorerie et découvert' => 0.0,
        ];
    }

    private function structureChargesVide(): array
    {
        return [
            'Achats de marchandises' => 0.0,
            'Variation de stocks (marchandises)' => 0.0,
            'Achats de matières premières et fournitures liées' => 0.0,
            'Variation de stocks (matières et autres approvisionnements)' => 0.0,
            'Autres achats' => 0.0,
            'Transports' => 0.0,
            'Services extérieurs' => 0.0,
            'Impôts et taxes' => 0.0,
            'Autres charges' => 0.0,
            'Charges de personnel' => 0.0,
            'Dotations aux amortissements et aux provisions' => 0.0,
            'Frais financiers et charges assimilées' => 0.0,
            'Participation des travailleurs' => 0.0,
            'Impôts sur le résultat' => 0.0,
        ];
    }

    private function structureProduitsVide(): array
    {
        return [
            'Ventes de marchandises' => 0.0,
            'Ventes de produits fabriqués' => 0.0,
            'Travaux, services vendus' => 0.0,
            'Production stockée' => 0.0,
            'Production immobilisée' => 0.0,
            'Produits accessoires' => 0.0,
            'Subventions d\'exploitation' => 0.0,
            'Autres produits' => 0.0,
            'Reprises de provisions' => 0.0,
            'Transferts de charges' => 0.0,
            'Revenus financiers et produits assimilés' => 0.0,
        ];
    }

    /**
     * Construit le Compte de Résultat au format "liste" avec soldes
     * intermédiaires de gestion (SIG), conforme au modèle SYSCOHADA système
     * normal : Marge brute, Chiffre d'affaires, Valeur ajoutée, Excédent
     * brut d'exploitation, Résultat d'exploitation, Résultat financier,
     * Résultat avant prélèvement, Résultat net.
     *
     * @param array<string, float> $charges  rubriques de structureChargesVide(), déjà alimentées
     * @param array<string, float> $produits rubriques de structureProduitsVide(), déjà alimentées
     *
     * @return array{lignes: list<array{ref: string, libelle: string, note: ?string, sens: string, montant: float, type: string}>, resultatNet: float}
     */
    private function construireCompteResultat(array $charges, array $produits): array
    {
        // Une rubrique garde toujours le sens de sa nature comptable (produit = +, charge = -),
        // même si le solde réel importé est anormal (ex. compte de vente en position débitrice) :
        // on force donc la grandeur en valeur absolue avant tout calcul, pour que le montant
        // affiché ne contredise jamais la colonne (2) et que les formules restent de simples sommes.
        $charges = array_map('abs', $charges);
        $produits = array_map('abs', $produits);

        $margeBrute = $produits['Ventes de marchandises']
            - $charges['Achats de marchandises']
            - $charges['Variation de stocks (marchandises)'];

        $chiffreAffaires = $produits['Ventes de marchandises']
            + $produits['Ventes de produits fabriqués']
            + $produits['Travaux, services vendus']
            + $produits['Produits accessoires'];

        $valeurAjoutee = $margeBrute
            + $produits['Ventes de produits fabriqués']
            + $produits['Travaux, services vendus']
            + $produits['Production stockée']
            + $produits['Production immobilisée']
            + $produits['Produits accessoires']
            + $produits["Subventions d'exploitation"]
            + $produits['Autres produits']
            - $charges['Achats de matières premières et fournitures liées']
            - $charges['Variation de stocks (matières et autres approvisionnements)']
            - $charges['Autres achats']
            - $charges['Transports']
            - $charges['Services extérieurs']
            - $charges['Impôts et taxes']
            - $charges['Autres charges'];

        $excedentBrutExploitation = $valeurAjoutee - $charges['Charges de personnel'];

        $resultatExploitation = $excedentBrutExploitation
            + $produits['Reprises de provisions']
            + $produits['Transferts de charges']
            - $charges['Dotations aux amortissements et aux provisions'];

        $resultatFinancier = $produits['Revenus financiers et produits assimilés']
            - $charges['Frais financiers et charges assimilées'];

        $resultatAvantPrelevement = $resultatExploitation + $resultatFinancier;

        $resultatNet = $resultatAvantPrelevement
            - $charges['Participation des travailleurs']
            - $charges['Impôts sur le résultat'];

        // REF : codification standard SYSCOHADA (T.. = produit, R.. = charge, X.. = solde
        // intermédiaire de gestion). "sens" (colonne 2) est un attribut fixe de la rubrique
        // (+ produit / − charge / = solde) et ne dépend jamais du signe réel du solde importé.
        $lignes = [
            ['ref' => 'TA', 'libelle' => 'Ventes de marchandises', 'note' => 'A', 'sens' => '+', 'montant' => $produits['Ventes de marchandises']],
            ['ref' => 'RA', 'libelle' => 'Achats de marchandises', 'note' => null, 'sens' => '-', 'montant' => -$charges['Achats de marchandises']],
            ['ref' => 'RB', 'libelle' => 'Variation de stocks', 'note' => null, 'sens' => '-', 'montant' => -$charges['Variation de stocks (marchandises)']],
            ['ref' => 'XA', 'libelle' => 'MARGE BRUTE (TA + RA + RB)', 'note' => null, 'sens' => '=', 'montant' => $margeBrute, 'type' => 'total'],

            ['ref' => 'TB', 'libelle' => 'Ventes de produits fabriqués', 'note' => 'B', 'sens' => '+', 'montant' => $produits['Ventes de produits fabriqués']],
            ['ref' => 'TC', 'libelle' => 'Travaux, services vendus', 'note' => 'C', 'sens' => '+', 'montant' => $produits['Travaux, services vendus']],
            ['ref' => 'TD', 'libelle' => 'Production stockée (ou déstockage)', 'note' => null, 'sens' => '+', 'montant' => $produits['Production stockée']],
            ['ref' => 'TE', 'libelle' => 'Production immobilisée', 'note' => null, 'sens' => '+', 'montant' => $produits['Production immobilisée']],
            ['ref' => 'TF', 'libelle' => 'Produits accessoires', 'note' => 'D', 'sens' => '+', 'montant' => $produits['Produits accessoires']],
            ['ref' => 'TG', 'libelle' => "Subventions d'exploitation", 'note' => null, 'sens' => '+', 'montant' => $produits["Subventions d'exploitation"]],
            ['ref' => 'TH', 'libelle' => 'Autres produits', 'note' => null, 'sens' => '+', 'montant' => $produits['Autres produits']],
            ['ref' => 'XB', 'libelle' => "CHIFFRE D'AFFAIRES (TA + TB + TC + TF)", 'note' => null, 'sens' => '=', 'montant' => $chiffreAffaires, 'type' => 'total'],

            ['ref' => 'RC', 'libelle' => 'Achats de matières premières et fournitures liées', 'note' => null, 'sens' => '-', 'montant' => -$charges['Achats de matières premières et fournitures liées']],
            ['ref' => 'RD', 'libelle' => 'Variation de stocks', 'note' => null, 'sens' => '-', 'montant' => -$charges['Variation de stocks (matières et autres approvisionnements)']],
            ['ref' => 'RE', 'libelle' => 'Autres achats', 'note' => null, 'sens' => '-', 'montant' => -$charges['Autres achats']],
            ['ref' => 'RF', 'libelle' => 'Transports', 'note' => null, 'sens' => '-', 'montant' => -$charges['Transports']],
            ['ref' => 'RG', 'libelle' => 'Services extérieurs', 'note' => null, 'sens' => '-', 'montant' => -$charges['Services extérieurs']],
            ['ref' => 'RH', 'libelle' => 'Impôts et taxes', 'note' => null, 'sens' => '-', 'montant' => -$charges['Impôts et taxes']],
            ['ref' => 'RI', 'libelle' => 'Autres charges', 'note' => null, 'sens' => '-', 'montant' => -$charges['Autres charges']],
            ['ref' => 'XC', 'libelle' => 'VALEUR AJOUTÉE (XA + TB + TC + TD + TE + TF + TG + TH + RC + RD + RE + RF + RG + RH + RI)', 'note' => null, 'sens' => '=', 'montant' => $valeurAjoutee, 'type' => 'total'],

            ['ref' => 'RJ', 'libelle' => 'Charges de personnel', 'note' => null, 'sens' => '-', 'montant' => -$charges['Charges de personnel']],
            ['ref' => 'XD', 'libelle' => "EXCÉDENT BRUT D'EXPLOITATION (XC + RJ)", 'note' => null, 'sens' => '=', 'montant' => $excedentBrutExploitation, 'type' => 'total'],

            ['ref' => 'TI', 'libelle' => 'Reprises de provisions', 'note' => null, 'sens' => '+', 'montant' => $produits['Reprises de provisions']],
            ['ref' => 'TJ', 'libelle' => 'Transferts de charges', 'note' => null, 'sens' => '+', 'montant' => $produits['Transferts de charges']],
            ['ref' => 'RK', 'libelle' => 'Dotations aux amortissements et aux provisions', 'note' => null, 'sens' => '-', 'montant' => -$charges['Dotations aux amortissements et aux provisions']],
            ['ref' => 'XE', 'libelle' => "RÉSULTAT D'EXPLOITATION (XD + TI + TJ + RK)", 'note' => null, 'sens' => '=', 'montant' => $resultatExploitation, 'type' => 'total'],

            ['ref' => 'TK', 'libelle' => 'Revenus financiers et produits assimilés', 'note' => null, 'sens' => '+', 'montant' => $produits['Revenus financiers et produits assimilés']],
            ['ref' => 'RL', 'libelle' => 'Frais financiers et charges assimilées', 'note' => null, 'sens' => '-', 'montant' => -$charges['Frais financiers et charges assimilées']],
            ['ref' => 'XF', 'libelle' => 'RÉSULTAT FINANCIER (TK + RL)', 'note' => null, 'sens' => '=', 'montant' => $resultatFinancier, 'type' => 'total'],
            ['ref' => 'XG', 'libelle' => 'RÉSULTAT AVANT PRÉLÈVEMENT (XE + XF)', 'note' => null, 'sens' => '=', 'montant' => $resultatAvantPrelevement, 'type' => 'total'],

            ['ref' => 'RM', 'libelle' => 'Participation des travailleurs', 'note' => null, 'sens' => '-', 'montant' => -$charges['Participation des travailleurs']],
            ['ref' => 'RN', 'libelle' => 'Impôts sur le résultat', 'note' => null, 'sens' => '-', 'montant' => -$charges['Impôts sur le résultat']],
            ['ref' => 'XI', 'libelle' => 'RÉSULTAT NET (XG + RM + RN)', 'note' => null, 'sens' => '=', 'montant' => $resultatNet, 'type' => 'total'],
        ];

        foreach ($lignes as &$ligne) {
            $ligne['type'] = $ligne['type'] ?? 'ligne';
            $ligne['montant'] = round($ligne['montant'], 2);
        }
        unset($ligne);

        return ['lignes' => $lignes, 'resultatNet' => round($resultatNet, 2)];
    }

    /**
     * Construit le Bilan Actif au format "liste" avec REF : Brut / Amortissements-Provisions /
     * Net par rubrique, puis Total Actif Immobilisé, Total Actif Circulant, Total Trésorerie
     * Actif et Total Général, conformément au modèle SYSCOHADA système normal.
     *
     * REF : codification maison (AA.. immobilisé, BA.. circulant, CA.. trésorerie, ..Z pour les
     * soldes), dans le même esprit que le compte de résultat — non garantie identique lettre
     * pour lettre à l'annexe officielle à ce niveau de détail des sous-rubriques.
     *
     * @param array<string, array{brut: float, amortProv: float}> $actif rubriques de structureActifVide(), déjà alimentées
     *
     * @return array{lignes: list<array{ref: string, libelle: string, brut: ?float, amortProv: ?float, montant: float, type: string}>, totalActif: float}
     */
    private function construireBilanActif(array $actif): array
    {
        $lignes = [];

        $section = function (array $rubriques, string $refTotal, string $libelleTotal) use ($actif, &$lignes): array {
            $brutTotal = 0.0;
            $amortProvTotal = 0.0;

            foreach ($rubriques as [$ref, $nom]) {
                $brut = $actif[$nom]['brut'];
                $amortProv = $actif[$nom]['amortProv'];
                $brutTotal += $brut;
                $amortProvTotal += $amortProv;
                $lignes[] = ['ref' => $ref, 'libelle' => $nom, 'brut' => $brut, 'amortProv' => $amortProv, 'montant' => $brut - $amortProv, 'type' => 'ligne'];
            }

            // Formule affichée sous le titre du sous-total, comme pour le compte de résultat.
            $formule = implode(' + ', array_column($rubriques, 0));
            $lignes[] = ['ref' => $refTotal, 'libelle' => "$libelleTotal ($formule)", 'brut' => $brutTotal, 'amortProv' => $amortProvTotal, 'montant' => $brutTotal - $amortProvTotal, 'type' => 'total'];

            return [$brutTotal, $amortProvTotal];
        };

        [$immoBrut, $immoAmortProv] = $section([
            ['AA', 'Brevets, Licences'],
            ['AB', 'Logiciels'],
            ['AC', 'Terrains'],
            ['AD', 'Bâtiments'],
            ['AE', 'Installations et aménagements'],
            ['AF', 'Matériel et Outillage'],
            ['AG', 'Matériel, Mobilier de bureau'],
            ['AH', 'Matériel informatique'],
            ['AI', 'Immobilisations corporelles en cours'],
            ['AJ', 'Avances et acomptes'],
            ['AK', 'Titres de participation'],
            ['AL', 'Autres immobilisations financières'],
        ], 'AZ', 'TOTAL ACTIF IMMOBILISE');

        [$circBrut, $circAmortProv] = $section([
            ['BA', 'Stocks et encours'],
            ['BB', 'Fournisseurs avances versées'],
            ['BC', 'Clients'],
            ['BD', 'autres créances'],
        ], 'BZ', 'TOTAL ACTIF CIRCULANT');

        [$tresBrut, $tresAmortProv] = $section([
            ['CA', 'Titres de placement'],
            ['CB', 'Disponibilités'],
        ], 'CZ', 'TOTAL TRESORERIE ACTIF');

        $totalGeneralBrut = $immoBrut + $circBrut + $tresBrut;
        $totalGeneralAmortProv = $immoAmortProv + $circAmortProv + $tresAmortProv;
        $totalGeneralNet = $totalGeneralBrut - $totalGeneralAmortProv;

        $lignes[] = ['ref' => 'ZZ', 'libelle' => 'TOTAL GENERAL (AZ + BZ + CZ)', 'brut' => $totalGeneralBrut, 'amortProv' => $totalGeneralAmortProv, 'montant' => $totalGeneralNet, 'type' => 'total'];

        foreach ($lignes as &$ligne) {
            $ligne['brut'] = round($ligne['brut'], 2);
            $ligne['amortProv'] = round($ligne['amortProv'], 2);
            $ligne['montant'] = round($ligne['montant'], 2);
        }
        unset($ligne);

        return ['lignes' => $lignes, 'totalActif' => round($totalGeneralNet, 2)];
    }

    /**
     * Construit le Bilan Passif au format "liste" avec REF (PA.. capitaux propres, QA.. dettes
     * financières, RA.. passif circulant, SA.. trésorerie passif, ..Z pour les soldes).
     *
     * @param array<string, float> $passif rubriques de structureBilanPassifVide(), déjà alimentées
     *
     * @return array{lignes: list<array{ref: string, libelle: string, montant: float, type: string}>, totalPassif: float}
     */
    private function construireBilanPassif(array $passif): array
    {
        $lignes = [];

        $section = function (array $rubriques, string $refTotal, string $libelleTotal) use ($passif, &$lignes): float {
            $total = 0.0;

            foreach ($rubriques as [$ref, $nom]) {
                $total += $passif[$nom];
                $lignes[] = ['ref' => $ref, 'libelle' => $nom, 'montant' => $passif[$nom], 'type' => 'ligne'];
            }

            // Formule affichée sous le titre du sous-total, comme pour le compte de résultat.
            $formule = implode(' + ', array_column($rubriques, 0));
            $lignes[] = ['ref' => $refTotal, 'libelle' => "$libelleTotal ($formule)", 'montant' => $total, 'type' => 'total'];

            return $total;
        };

        $totalCapitauxPropres = $section([
            ['PA', 'Capital'],
            ['PB', 'Actionnaires capital non appelé'],
            ['PC', "Primes d'apport, d'émission, de fusion"],
            ['PD', 'Ecarts de réévaluation'],
            ['PE', 'Réserves indisponibles'],
            ['PF', 'Réserves libres'],
            ['PG', 'Report à nouveau'],
            ['PH', "Résultat en instance d'affectation"],
            ['PI', "Résultat net de l'exercice"],
            ['PJ', 'Provisions réglementées'],
        ], 'PZ', 'TOTAL CAPITAUX PROPRES ET RESSOURCES ASSIMILEES');

        $totalDettesFinancieres = $section([
            ['QA', "Subventions d'investissement"],
            ['QB', 'Emprunts et dettes financières'],
            ['QC', 'Dettes de crédit-bail et contrats assimilés'],
            ['QD', 'Provisions financières pour risques et charges'],
        ], 'QZ', 'TOTAL DETTES FINANCIERES ET RESSOURCES ASSIMILEES');

        $totalPassifCirculant = $section([
            ['RA', 'Clients, avances reçues'],
            ['RB', "Fournisseurs d'exploitation"],
            ['RC', 'Dettes fiscales et sociales'],
            ['RD', 'Autres dettes'],
            ['RE', 'Risques provisionnés'],
        ], 'RZ', 'TOTAL PASSIF CIRCULANT');

        $totalTresoreriePassif = $section([
            ['SA', "Banques, crédits d'escompte"],
            ['SB', 'Banques, crédits de trésorerie et découvert'],
        ], 'SZ', 'TOTAL TRESORERIE PASSIF');

        $totalGeneral = $totalCapitauxPropres + $totalDettesFinancieres + $totalPassifCirculant + $totalTresoreriePassif;

        $lignes[] = ['ref' => 'ZZ', 'libelle' => 'TOTAL GENERAL (PZ + QZ + RZ + SZ)', 'montant' => $totalGeneral, 'type' => 'total'];

        foreach ($lignes as &$ligne) {
            $ligne['montant'] = round($ligne['montant'], 2);
        }
        unset($ligne);

        return ['lignes' => $lignes, 'totalPassif' => round($totalGeneral, 2)];
    }
}

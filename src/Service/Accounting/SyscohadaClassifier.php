<?php

namespace App\Service\Accounting;

/**
 * Classe chaque ligne de balance dans une rubrique du Bilan ou du Compte
 * de Résultat, selon le plan comptable SYSCOHADA révisé (classes 1 à 8).
 *
 * Les comptes de tiers (classe 4) et de trésorerie (classe 5) sont
 * ventilés selon le sens réel de leur solde plutôt que selon leur seul
 * numéro : un compte fournisseur au solde débiteur est économiquement
 * une créance, et inversement pour un compte client créditeur.
 *
 * La classe 9 (engagements hors bilan / comptabilité analytique) est
 * hors périmètre des états financiers et n'est jamais classée.
 */
class SyscohadaClassifier
{
    public function classifier(BalanceRow $ligne): ?AccountClassification
    {
        $compte = preg_replace('/\D/', '', $ligne->compte) ?? '';
        if ($compte === '') {
            return null;
        }

        return match ($compte[0]) {
            '1' => $this->classifierClasse1($compte, $ligne),
            '2' => $this->classifierClasse2($compte, $ligne),
            '3' => $this->classifierClasse3($compte, $ligne),
            '4' => $this->classifierClasse4($compte, $ligne),
            '5' => $this->classifierClasse5($compte, $ligne),
            '6' => $this->classifierClasse6($compte, $ligne),
            '7' => $this->classifierClasse7($compte, $ligne),
            '8' => $this->classifierClasse8($compte, $ligne),
            default => null, // classe 0 et 9 : hors états financiers
        };
    }

    private function classifierClasse1(string $compte, BalanceRow $ligne): ?AccountClassification
    {
        // Le résultat net est recalculé (produits - charges) plutôt que repris du fichier.
        if (str_starts_with($compte, '13')) {
            return null;
        }

        // Compte 109 : actionnaires, capital souscrit non appelé — une créance sur les
        // actionnaires qui vient en DÉDUCTION des capitaux propres (soldeDebit normalement).
        if (str_starts_with($compte, '109')) {
            $montant = -($ligne->soldeDebit - $ligne->soldeCredit);

            return new AccountClassification(AccountClassification::SECTION_PASSIF, 'Actionnaires capital non appelé', $montant);
        }

        $montant = $ligne->soldeCredit - $ligne->soldeDebit;

        return match (true) {
            // Les préfixes les plus spécifiques (105, 106) doivent être testés avant le
            // préfixe générique '10', sous peine d'être toujours absorbés par celui-ci.
            str_starts_with($compte, '105') => new AccountClassification(AccountClassification::SECTION_PASSIF, "Primes d'apport, d'émission, de fusion", $montant),
            str_starts_with($compte, '106') => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Ecarts de réévaluation', $montant),
            str_starts_with($compte, '10') => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Capital', $montant),
            str_starts_with($compte, '118') => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Réserves libres', $montant),
            str_starts_with($compte, '11') => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Réserves indisponibles', $montant),
            str_starts_with($compte, '12') => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Report à nouveau', $montant),
            str_starts_with($compte, '14') => new AccountClassification(AccountClassification::SECTION_PASSIF, "Subventions d'investissement", $montant),
            str_starts_with($compte, '15') => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Provisions réglementées', $montant),
            str_starts_with($compte, '17') => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Dettes de crédit-bail et contrats assimilés', $montant),
            str_starts_with($compte, '16'), str_starts_with($compte, '18') => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Emprunts et dettes financières', $montant),
            str_starts_with($compte, '19') => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Provisions financières pour risques et charges', $montant),
            default => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Capital', $montant),
        };
    }

    /**
     * Classe 2 : immobilisations (Actif, colonne Brut) et leurs amortissements/provisions
     * (comptes 28 et 29, colonne Amort/Prov) — chaque compte d'amortissement est rattaché à
     * la MÊME rubrique que l'immobilisation qu'il déprécie (ex. 2834 déprécie les
     * "Installations et aménagements" comme le fait le compte 233/234).
     */
    private function classifierClasse2(string $compte, BalanceRow $ligne): AccountClassification
    {
        if (str_starts_with($compte, '28') || str_starts_with($compte, '29')) {
            $montant = $ligne->soldeCredit - $ligne->soldeDebit;
            $rubrique = match (true) {
                str_starts_with($compte, '2813'), str_starts_with($compte, '2811'), str_starts_with($compte, '2812') => 'Brevets, Licences',
                str_starts_with($compte, '2815'), str_starts_with($compte, '2818') => 'Logiciels',
                str_starts_with($compte, '281') => 'Brevets, Licences',
                str_starts_with($compte, '282') => 'Terrains',
                str_starts_with($compte, '2831'), str_starts_with($compte, '2832') => 'Bâtiments',
                str_starts_with($compte, '283'), str_starts_with($compte, '2834'), str_starts_with($compte, '2835'), str_starts_with($compte, '2838') => 'Installations et aménagements',
                str_starts_with($compte, '2841') => 'Matériel et Outillage',
                str_starts_with($compte, '2844') => 'Matériel, Mobilier de bureau',
                str_starts_with($compte, '2845') => 'Matériel informatique',
                str_starts_with($compte, '284'), str_starts_with($compte, '285'), str_starts_with($compte, '288') => 'Matériel et Outillage',
                str_starts_with($compte, '291') => 'Titres de participation',
                str_starts_with($compte, '29') => 'Autres immobilisations financières',
                default => 'Matériel et Outillage',
            };

            return new AccountClassification(AccountClassification::SECTION_ACTIF, $rubrique, $montant, AccountClassification::COLONNE_AMORT_PROV);
        }

        $montant = $ligne->soldeDebit - $ligne->soldeCredit;

        $rubrique = match (true) {
            str_starts_with($compte, '2125'), str_starts_with($compte, '2128') => 'Logiciels',
            str_starts_with($compte, '20'), str_starts_with($compte, '21') => 'Brevets, Licences',
            // '229' doit être testé avant le préfixe générique '22', sous peine d'être absorbé par lui.
            str_starts_with($compte, '229'), str_starts_with($compte, '239'), str_starts_with($compte, '249') => 'Immobilisations corporelles en cours',
            str_starts_with($compte, '22') => 'Terrains',
            str_starts_with($compte, '231'), str_starts_with($compte, '232') => 'Bâtiments',
            str_starts_with($compte, '233'), str_starts_with($compte, '234'), str_starts_with($compte, '235'), str_starts_with($compte, '238') => 'Installations et aménagements',
            str_starts_with($compte, '241') => 'Matériel et Outillage',
            str_starts_with($compte, '2441') => 'Matériel, Mobilier de bureau',
            str_starts_with($compte, '2444'), str_starts_with($compte, '2445') => 'Matériel informatique',
            str_starts_with($compte, '244') => 'Matériel, Mobilier de bureau',
            str_starts_with($compte, '245'), str_starts_with($compte, '246'), str_starts_with($compte, '248') => 'Matériel et Outillage',
            str_starts_with($compte, '25') => 'Avances et acomptes',
            str_starts_with($compte, '26') => 'Titres de participation',
            str_starts_with($compte, '27') => 'Autres immobilisations financières',
            default => 'Matériel et Outillage',
        };

        return new AccountClassification(AccountClassification::SECTION_ACTIF, $rubrique, $montant, AccountClassification::COLONNE_BRUT);
    }

    private function classifierClasse3(string $compte, BalanceRow $ligne): AccountClassification
    {
        if (str_starts_with($compte, '39')) {
            $montant = $ligne->soldeCredit - $ligne->soldeDebit;

            return new AccountClassification(AccountClassification::SECTION_ACTIF, 'Stocks et encours', $montant, AccountClassification::COLONNE_AMORT_PROV);
        }

        return new AccountClassification(AccountClassification::SECTION_ACTIF, 'Stocks et encours', $ligne->soldeDebit - $ligne->soldeCredit, AccountClassification::COLONNE_BRUT);
    }

    /**
     * Classe 4 : comptes de tiers. Ventilés par sous-compte (fournisseurs, clients, fiscal/
     * social, autres) puis, dans chaque sous-compte, selon le sens réel du solde — un compte
     * fournisseur au solde débiteur est économiquement une créance, et inversement pour un
     * compte client créditeur.
     */
    private function classifierClasse4(string $compte, BalanceRow $ligne): AccountClassification
    {
        // 491 à 498 : dépréciations des comptes de tiers, viennent en déduction des créances.
        if (str_starts_with($compte, '49') && !str_starts_with($compte, '499')) {
            $montant = $ligne->soldeCredit - $ligne->soldeDebit;
            $rubrique = str_starts_with($compte, '491') ? 'Clients' : 'autres créances';

            return new AccountClassification(AccountClassification::SECTION_ACTIF, $rubrique, $montant, AccountClassification::COLONNE_AMORT_PROV);
        }

        // 499 : risques provisionnés (provisions à court terme sur comptes de tiers) — Passif circulant.
        if (str_starts_with($compte, '499')) {
            return new AccountClassification(AccountClassification::SECTION_PASSIF, 'Risques provisionnés', $ligne->soldeCredit - $ligne->soldeDebit);
        }

        $solde = $ligne->soldeDebit - $ligne->soldeCredit;

        // 401/408 : fournisseurs — normalement créditeurs (dette) ; débiteurs, c'est une avance versée.
        if (str_starts_with($compte, '401') || str_starts_with($compte, '408')) {
            return $solde >= 0
                ? new AccountClassification(AccountClassification::SECTION_ACTIF, 'Fournisseurs avances versées', $solde, AccountClassification::COLONNE_BRUT)
                : new AccountClassification(AccountClassification::SECTION_PASSIF, "Fournisseurs d'exploitation", -$solde);
        }

        // 4091 : avances et acomptes versés aux fournisseurs — toujours une créance (Actif).
        if (str_starts_with($compte, '4091')) {
            return new AccountClassification(AccountClassification::SECTION_ACTIF, 'Fournisseurs avances versées', $solde, AccountClassification::COLONNE_BRUT);
        }

        // 4191 : avances et acomptes reçus des clients — toujours une dette (Passif).
        if (str_starts_with($compte, '4191')) {
            return new AccountClassification(AccountClassification::SECTION_PASSIF, 'Clients, avances reçues', -$solde);
        }

        // 411/416/418 : clients — normalement débiteurs (créance) ; créditeurs, c'est une avance reçue.
        if (str_starts_with($compte, '411') || str_starts_with($compte, '416') || str_starts_with($compte, '418')) {
            return $solde >= 0
                ? new AccountClassification(AccountClassification::SECTION_ACTIF, 'Clients', $solde, AccountClassification::COLONNE_BRUT)
                : new AccountClassification(AccountClassification::SECTION_PASSIF, 'Clients, avances reçues', -$solde);
        }

        // 43/44 : organismes sociaux et État — normalement créditeurs (dette fiscale et sociale).
        if (str_starts_with($compte, '43') || str_starts_with($compte, '44')) {
            return $solde >= 0
                ? new AccountClassification(AccountClassification::SECTION_ACTIF, 'autres créances', $solde, AccountClassification::COLONNE_BRUT)
                : new AccountClassification(AccountClassification::SECTION_PASSIF, 'Dettes fiscales et sociales', -$solde);
        }

        // 42, 45, 46, 47, 48 : personnel, groupe, débiteurs/créditeurs divers, HAO.
        return $solde >= 0
            ? new AccountClassification(AccountClassification::SECTION_ACTIF, 'autres créances', $solde, AccountClassification::COLONNE_BRUT)
            : new AccountClassification(AccountClassification::SECTION_PASSIF, 'Autres dettes', -$solde);
    }

    /**
     * Classe 5 : trésorerie. 50 = titres de placement, 51/52/53/57 = disponibilités
     * (banques, chèques postaux, caisse), 56 = crédits d'escompte/de trésorerie (Passif),
     * 59 = dépréciations des titres de placement.
     */
    private function classifierClasse5(string $compte, BalanceRow $ligne): AccountClassification
    {
        if (str_starts_with($compte, '59')) {
            $montant = $ligne->soldeCredit - $ligne->soldeDebit;

            return new AccountClassification(AccountClassification::SECTION_ACTIF, 'Titres de placement', $montant, AccountClassification::COLONNE_AMORT_PROV);
        }

        if (str_starts_with($compte, '50')) {
            return new AccountClassification(AccountClassification::SECTION_ACTIF, 'Titres de placement', $ligne->soldeDebit - $ligne->soldeCredit, AccountClassification::COLONNE_BRUT);
        }

        if (str_starts_with($compte, '564')) {
            return new AccountClassification(AccountClassification::SECTION_PASSIF, "Banques, crédits d'escompte", $ligne->soldeCredit - $ligne->soldeDebit);
        }

        if (str_starts_with($compte, '56')) {
            return new AccountClassification(AccountClassification::SECTION_PASSIF, 'Banques, crédits de trésorerie et découvert', $ligne->soldeCredit - $ligne->soldeDebit);
        }

        // 51 (valeurs à encaisser), 52 (banques), 53 (établissements financiers), 57 (caisse) :
        // normalement débiteurs (disponibilités) ; un compte bancaire créditeur est un découvert.
        $solde = $ligne->soldeDebit - $ligne->soldeCredit;

        if ($solde >= 0) {
            return new AccountClassification(AccountClassification::SECTION_ACTIF, 'Disponibilités', $solde, AccountClassification::COLONNE_BRUT);
        }

        return new AccountClassification(AccountClassification::SECTION_PASSIF, 'Banques, crédits de trésorerie et découvert', -$solde);
    }

    private function classifierClasse6(string $compte, BalanceRow $ligne): AccountClassification
    {
        $montant = $ligne->soldeDebit - $ligne->soldeCredit;

        return match (true) {
            str_starts_with($compte, '601') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Achats de marchandises', $montant),
            // 6031 : variation de stocks de marchandises (intervient dans la Marge brute) ;
            // 6032/6033/... : variation de stocks de matières et autres approvisionnements
            // (intervient dans la Valeur ajoutée) — l'ordre des branches ci-dessous est important.
            str_starts_with($compte, '6031') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Variation de stocks (marchandises)', $montant),
            str_starts_with($compte, '602') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Achats de matières premières et fournitures liées', $montant),
            str_starts_with($compte, '603') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Variation de stocks (matières et autres approvisionnements)', $montant),
            str_starts_with($compte, '60') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Autres achats', $montant),
            str_starts_with($compte, '61') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Transports', $montant),
            str_starts_with($compte, '62'), str_starts_with($compte, '63') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Services extérieurs', $montant),
            str_starts_with($compte, '64') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Impôts et taxes', $montant),
            str_starts_with($compte, '65') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Autres charges', $montant),
            str_starts_with($compte, '66') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Charges de personnel', $montant),
            str_starts_with($compte, '67') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Frais financiers et charges assimilées', $montant),
            str_starts_with($compte, '68') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Dotations aux amortissements et aux provisions', $montant),
            str_starts_with($compte, '69') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Impôts sur le résultat', $montant),
            default => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Autres charges', $montant),
        };
    }

    private function classifierClasse7(string $compte, BalanceRow $ligne): AccountClassification
    {
        $montant = $ligne->soldeCredit - $ligne->soldeDebit;

        return match (true) {
            str_starts_with($compte, '701') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Ventes de marchandises', $montant),
            str_starts_with($compte, '702'), str_starts_with($compte, '703'), str_starts_with($compte, '704') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Ventes de produits fabriqués', $montant),
            str_starts_with($compte, '705'), str_starts_with($compte, '706') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Travaux, services vendus', $montant),
            str_starts_with($compte, '707') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Produits accessoires', $montant),
            str_starts_with($compte, '71') => new AccountClassification(AccountClassification::SECTION_PRODUITS, "Subventions d'exploitation", $montant),
            str_starts_with($compte, '72') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Production immobilisée', $montant),
            str_starts_with($compte, '73') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Production stockée', $montant),
            str_starts_with($compte, '75') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Autres produits', $montant),
            str_starts_with($compte, '77') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Revenus financiers et produits assimilés', $montant),
            str_starts_with($compte, '78') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Transferts de charges', $montant),
            str_starts_with($compte, '79') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Reprises de provisions', $montant),
            default => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Autres produits', $montant),
        };
    }

    private function classifierClasse8(string $compte, BalanceRow $ligne): AccountClassification
    {
        if (str_starts_with($compte, '89')) {
            return new AccountClassification(AccountClassification::SECTION_CHARGES, 'Impôts sur le résultat', $ligne->soldeDebit - $ligne->soldeCredit);
        }

        // Compte 87 : participation des travailleurs aux bénéfices — une charge,
        // déduite du Résultat net (et non un produit HAO comme avant ce correctif).
        if (str_starts_with($compte, '87')) {
            return new AccountClassification(AccountClassification::SECTION_CHARGES, 'Participation des travailleurs', $ligne->soldeDebit - $ligne->soldeCredit);
        }

        // Hors Activités Ordinaires (81, 83, 85, 88...) : le modèle de compte de
        // résultat retenu (SIG en liste) ne détaille pas de rubrique HAO séparée.
        // Ces montants, rares en pratique, sont versés dans "Autres charges"/
        // "Autres produits" pour que le résultat net reste exact.
        if (in_array(substr($compte, 0, 2), ['81', '83', '85', '88'], true)) {
            return new AccountClassification(AccountClassification::SECTION_CHARGES, 'Autres charges', $ligne->soldeDebit - $ligne->soldeCredit);
        }

        return new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Autres produits', $ligne->soldeCredit - $ligne->soldeDebit);
    }
}

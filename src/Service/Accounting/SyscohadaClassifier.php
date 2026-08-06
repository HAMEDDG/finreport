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

        $montant = $ligne->soldeCredit - $ligne->soldeDebit;

        return match (true) {
            str_starts_with($compte, '10') => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Capital', $montant),
            str_starts_with($compte, '11') => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Réserves', $montant),
            str_starts_with($compte, '12') => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Report à nouveau', $montant),
            str_starts_with($compte, '14') => new AccountClassification(AccountClassification::SECTION_PASSIF, "Subventions d'investissement", $montant),
            str_starts_with($compte, '15') => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Provisions réglementées', $montant),
            str_starts_with($compte, '16'), str_starts_with($compte, '17'), str_starts_with($compte, '18') => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Emprunts et dettes financières', $montant),
            str_starts_with($compte, '19') => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Provisions financières pour risques et charges', $montant),
            default => new AccountClassification(AccountClassification::SECTION_PASSIF, 'Capital', $montant),
        };
    }

    private function classifierClasse2(string $compte, BalanceRow $ligne): AccountClassification
    {
        if (str_starts_with($compte, '28') || str_starts_with($compte, '29')) {
            $montant = -($ligne->soldeCredit - $ligne->soldeDebit);

            return new AccountClassification(AccountClassification::SECTION_ACTIF, 'Amortissements et dépréciations (-)', $montant);
        }

        $montant = $ligne->soldeDebit - $ligne->soldeCredit;

        return match (true) {
            str_starts_with($compte, '20'), str_starts_with($compte, '21') => new AccountClassification(AccountClassification::SECTION_ACTIF, 'Immobilisations incorporelles', $montant),
            str_starts_with($compte, '26'), str_starts_with($compte, '27') => new AccountClassification(AccountClassification::SECTION_ACTIF, 'Immobilisations financières', $montant),
            default => new AccountClassification(AccountClassification::SECTION_ACTIF, 'Immobilisations corporelles', $montant),
        };
    }

    private function classifierClasse3(string $compte, BalanceRow $ligne): AccountClassification
    {
        if (str_starts_with($compte, '39')) {
            $montant = -($ligne->soldeCredit - $ligne->soldeDebit);

            return new AccountClassification(AccountClassification::SECTION_ACTIF, 'Dépréciations des stocks (-)', $montant);
        }

        return new AccountClassification(AccountClassification::SECTION_ACTIF, 'Stocks', $ligne->soldeDebit - $ligne->soldeCredit);
    }

    private function classifierClasse4(string $compte, BalanceRow $ligne): AccountClassification
    {
        if (str_starts_with($compte, '49')) {
            $montant = -($ligne->soldeCredit - $ligne->soldeDebit);

            return new AccountClassification(AccountClassification::SECTION_ACTIF, 'Créances et emplois assimilés', $montant);
        }

        $solde = $ligne->soldeDebit - $ligne->soldeCredit;

        if ($solde >= 0) {
            return new AccountClassification(AccountClassification::SECTION_ACTIF, 'Créances et emplois assimilés', $solde);
        }

        return new AccountClassification(AccountClassification::SECTION_PASSIF, 'Dettes circulantes', -$solde);
    }

    private function classifierClasse5(string $compte, BalanceRow $ligne): AccountClassification
    {
        if (str_starts_with($compte, '59')) {
            $montant = -($ligne->soldeCredit - $ligne->soldeDebit);

            return new AccountClassification(AccountClassification::SECTION_ACTIF, 'Banques, chèques postaux, caisse', $montant);
        }

        if (str_starts_with($compte, '50')) {
            return new AccountClassification(AccountClassification::SECTION_ACTIF, 'Titres de placement', $ligne->soldeDebit - $ligne->soldeCredit);
        }

        if (str_starts_with($compte, '51')) {
            return new AccountClassification(AccountClassification::SECTION_ACTIF, 'Valeurs à encaisser', $ligne->soldeDebit - $ligne->soldeCredit);
        }

        $solde = $ligne->soldeDebit - $ligne->soldeCredit;

        if ($solde >= 0) {
            return new AccountClassification(AccountClassification::SECTION_ACTIF, 'Banques, chèques postaux, caisse', $solde);
        }

        return new AccountClassification(AccountClassification::SECTION_PASSIF, 'Banques, crédits de trésorerie', -$solde);
    }

    private function classifierClasse6(string $compte, BalanceRow $ligne): AccountClassification
    {
        $montant = $ligne->soldeDebit - $ligne->soldeCredit;

        return match (true) {
            str_starts_with($compte, '601') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Achats de marchandises', $montant),
            str_starts_with($compte, '603') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Variation de stocks', $montant),
            str_starts_with($compte, '60') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Autres achats', $montant),
            str_starts_with($compte, '61') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Transports', $montant),
            str_starts_with($compte, '62'), str_starts_with($compte, '63') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Services extérieurs', $montant),
            str_starts_with($compte, '64') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Impôts et taxes', $montant),
            str_starts_with($compte, '65') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Autres charges', $montant),
            str_starts_with($compte, '66') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Charges de personnel', $montant),
            str_starts_with($compte, '67') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Charges financières', $montant),
            str_starts_with($compte, '68') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Dotations aux amortissements', $montant),
            str_starts_with($compte, '69') => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Impôts sur le résultat', $montant),
            default => new AccountClassification(AccountClassification::SECTION_CHARGES, 'Autres charges', $montant),
        };
    }

    private function classifierClasse7(string $compte, BalanceRow $ligne): AccountClassification
    {
        $montant = $ligne->soldeCredit - $ligne->soldeDebit;

        return match (true) {
            str_starts_with($compte, '701') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Ventes de marchandises', $montant),
            str_starts_with($compte, '702'), str_starts_with($compte, '703') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Ventes de produits fabriqués', $montant),
            str_starts_with($compte, '704'), str_starts_with($compte, '705'), str_starts_with($compte, '706') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Travaux, services vendus', $montant),
            str_starts_with($compte, '71') => new AccountClassification(AccountClassification::SECTION_PRODUITS, "Subventions d'exploitation", $montant),
            str_starts_with($compte, '72') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Production immobilisée', $montant),
            str_starts_with($compte, '73') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Production stockée', $montant),
            str_starts_with($compte, '75') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Autres produits', $montant),
            str_starts_with($compte, '77') => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Produits financiers', $montant),
            str_starts_with($compte, '79') => new AccountClassification(AccountClassification::SECTION_PRODUITS, "Reprises d'amortissements et provisions", $montant),
            default => new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Autres produits', $montant),
        };
    }

    private function classifierClasse8(string $compte, BalanceRow $ligne): AccountClassification
    {
        if (str_starts_with($compte, '89')) {
            return new AccountClassification(AccountClassification::SECTION_CHARGES, 'Impôts sur le résultat', $ligne->soldeDebit - $ligne->soldeCredit);
        }

        if (in_array(substr($compte, 0, 2), ['81', '83', '85', '88'], true)) {
            return new AccountClassification(AccountClassification::SECTION_CHARGES, 'Charges HAO', $ligne->soldeDebit - $ligne->soldeCredit);
        }

        return new AccountClassification(AccountClassification::SECTION_PRODUITS, 'Produits HAO', $ligne->soldeCredit - $ligne->soldeDebit);
    }
}

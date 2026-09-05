<?php

namespace App\Service\Accounting;

/**
 * Résultat du classement d'un compte de la balance dans une rubrique
 * des états financiers (Bilan Actif/Passif ou Compte de Résultat).
 */
final class AccountClassification
{
    public const SECTION_ACTIF = 'actif';
    public const SECTION_PASSIF = 'passif';
    public const SECTION_CHARGES = 'charges';
    public const SECTION_PRODUITS = 'produits';

    /** Colonnes du Bilan Actif (Brut / Amortissements-Provisions / Net). Sans objet ailleurs. */
    public const COLONNE_BRUT = 'brut';
    public const COLONNE_AMORT_PROV = 'amortProv';
    public const COLONNE_NET = 'net';

    public function __construct(
        public readonly string $section,
        public readonly string $rubrique,
        public readonly float $montant,
        /** Pour SECTION_ACTIF uniquement : quelle colonne (Brut/Amort-Prov/Net) alimente ce montant. */
        public readonly string $colonne = self::COLONNE_NET,
    ) {
    }
}

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

    public function __construct(
        public readonly string $section,
        public readonly string $rubrique,
        public readonly float $montant,
    ) {
    }
}

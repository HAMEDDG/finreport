<?php

namespace App\Service\Accounting;

/**
 * Une ligne normalisée de la balance comptable importée, quel que soit
 * le format d'origine (CSV, XLS, XLSX).
 */
final class BalanceRow
{
    public function __construct(
        public readonly string $compte,
        public readonly string $libelle,
        public readonly float $soldeDebitAvant,
        public readonly float $soldeCreditAvant,
        public readonly float $mouvementDebit,
        public readonly float $mouvementCredit,
        public readonly float $soldeDebit,
        public readonly float $soldeCredit,
    ) {
    }
}

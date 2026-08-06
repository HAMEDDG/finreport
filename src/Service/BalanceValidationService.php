<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Vérifie qu'un fichier de balance respecte les contraintes attendues
 * (format, taille). La vérification de la cohérence comptable (équilibre
 * débit/crédit, colonnes attendues) est effectuée séparément par
 * BalanceFileReader et ReportGenerationService lors de la génération du rapport.
 */
class BalanceValidationService
{
    private const EXTENSIONS_AUTORISEES = ['xlsx', 'xls', 'csv'];
    private const TAILLE_MAX_OCTETS = 20 * 1024 * 1024;

    /**
     * @return string[] Liste des erreurs (vide si le fichier est valide)
     */
    public function valider(UploadedFile $fichier): array
    {
        $erreurs = [];

        $extension = strtolower($fichier->getClientOriginalExtension());
        if (!in_array($extension, self::EXTENSIONS_AUTORISEES, true)) {
            $erreurs[] = sprintf('Extension ".%s" non autorisée.', $extension);
        }

        if ($fichier->getSize() > self::TAILLE_MAX_OCTETS) {
            $erreurs[] = 'Le fichier dépasse la taille maximale de 20 Mo.';
        }

        return $erreurs;
    }

    /**
     * Compte approximatif du nombre de lignes (CSV uniquement pour l'instant).
     */
    public function compterLignes(UploadedFile $fichier): ?int
    {
        $extension = strtolower($fichier->getClientOriginalExtension());

        if ('csv' !== $extension) {
            return null;
        }

        $lignes = @file($fichier->getPathname(), FILE_SKIP_EMPTY_LINES);

        return $lignes ? count($lignes) : 0;
    }
}

<?php

namespace App\Service\Accounting;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Lit un fichier de balance comptable (CSV, XLS ou XLSX) et le transforme
 * en une liste de BalanceRow, quel que soit le format d'origine.
 *
 * Colonnes attendues (l'ordre des colonnes dans le fichier n'importe pas,
 * seul l'intitulé de l'en-tête compte) :
 *   Compte général | Libellé compte | Solde Débit Avant Période |
 *   Solde Crédit Avant Période | Mouvements Débit | Mouvements Crédit |
 *   Solde Débit | Solde Crédit
 */
class BalanceFileReader
{
    /**
     * Règles de reconnaissance des colonnes par mots-clés plutôt que par phrase exacte,
     * pour supporter les formulations réelles (pluriel/singulier, abréviations "mvt",
     * ordre des mots différent...) sans dépendre d'un intitulé figé.
     *
     * - "requiert" : liste de groupes de mots-clés ; chaque groupe doit avoir au moins
     *   un mot-clé présent dans l'en-tête (ET entre groupes, OU au sein d'un groupe).
     * - "exclut" : si un de ces mots-clés est présent, l'en-tête ne peut pas correspondre
     *   à cette colonne (sert à distinguer par ex. "Solde Débit" de "Mouvements Débit").
     */
    private const REGLES_COLONNES = [
        'compte' => [
            'requiert' => [['compte']],
            'exclut' => ['libell'],
        ],
        'libelle' => [
            'requiert' => [['libell']],
            'exclut' => [],
        ],
        // "débiteur"/"créditeur" (adjectifs décrivant le sens du solde) sont une façon
        // courante de désigner le solde d'ouverture, tout comme "avant"/"ouverture".
        'soldeDebitAvant' => [
            'requiert' => [['debit'], ['avant', 'ouvertur', 'initial', 'debiteur']],
            'exclut' => [],
        ],
        'soldeCreditAvant' => [
            'requiert' => [['credit'], ['avant', 'ouvertur', 'initial', 'crediteur']],
            'exclut' => [],
        ],
        'mouvementDebit' => [
            'requiert' => [['debit'], ['mouvement', 'mvt']],
            'exclut' => ['avant', 'ouvertur', 'initial', 'debiteur'],
        ],
        'mouvementCredit' => [
            'requiert' => [['credit'], ['mouvement', 'mvt']],
            'exclut' => ['avant', 'ouvertur', 'initial', 'crediteur'],
        ],
        'soldeDebit' => [
            'requiert' => [['debit']],
            'exclut' => ['avant', 'ouvertur', 'initial', 'mouvement', 'mvt', 'debiteur'],
        ],
        'soldeCredit' => [
            'requiert' => [['credit']],
            'exclut' => ['avant', 'ouvertur', 'initial', 'mouvement', 'mvt', 'crediteur'],
        ],
    ];

    private const LIBELLES_COLONNES = [
        'compte' => 'Compte général',
        'libelle' => 'Libellé compte',
        'soldeDebitAvant' => 'Solde Débit Avant Période',
        'soldeCreditAvant' => 'Solde Crédit Avant Période',
        'mouvementDebit' => 'Mouvements Débit',
        'mouvementCredit' => 'Mouvements Crédit',
        'soldeDebit' => 'Solde Débit',
        'soldeCredit' => 'Solde Crédit',
    ];

    /**
     * @return BalanceRow[]
     *
     * @throws \RuntimeException si le fichier est illisible ou si les colonnes
     *                           attendues sont introuvables dans l'en-tête
     */
    public function lire(string $cheminFichier, string $extension): array
    {
        $grille = match (strtolower($extension)) {
            'csv' => $this->lireCsv($cheminFichier),
            'xls', 'xlsx' => $this->lireTableur($cheminFichier),
            default => throw new \RuntimeException(sprintf('Format de fichier ".%s" non pris en charge pour le calcul du rapport.', $extension)),
        };

        if (empty($grille)) {
            throw new \RuntimeException('Le fichier de balance est vide.');
        }

        $indexColonnes = $this->resoudreColonnes($grille[0]);

        $lignes = [];
        for ($i = 1; $i < count($grille); $i++) {
            $ligne = $grille[$i];

            $compte = trim((string) ($ligne[$indexColonnes['compte']] ?? ''));

            // Ignore les lignes qui ne sont pas de vrais comptes : lignes vides, séparateurs,
            // ou lignes de total/sous-total ("Total", "Total classe 6"...) parfois présentes
            // dans les exports — leur numéro de compte n'est jamais purement numérique.
            if ($compte === '' || !preg_match('/^\d+$/', $compte)) {
                continue;
            }

            $lignes[] = new BalanceRow(
                compte: $compte,
                libelle: trim((string) ($ligne[$indexColonnes['libelle']] ?? '')),
                soldeDebitAvant: $this->normaliserNombre($ligne[$indexColonnes['soldeDebitAvant']] ?? 0),
                soldeCreditAvant: $this->normaliserNombre($ligne[$indexColonnes['soldeCreditAvant']] ?? 0),
                mouvementDebit: $this->normaliserNombre($ligne[$indexColonnes['mouvementDebit']] ?? 0),
                mouvementCredit: $this->normaliserNombre($ligne[$indexColonnes['mouvementCredit']] ?? 0),
                soldeDebit: $this->normaliserNombre($ligne[$indexColonnes['soldeDebit']] ?? 0),
                soldeCredit: $this->normaliserNombre($ligne[$indexColonnes['soldeCredit']] ?? 0),
            );
        }

        if (empty($lignes)) {
            throw new \RuntimeException('Aucune ligne de compte exploitable trouvée dans le fichier de balance.');
        }

        return $lignes;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function lireCsv(string $cheminFichier): array
    {
        $contenu = file_get_contents($cheminFichier);
        if ($contenu === false) {
            throw new \RuntimeException('Impossible de lire le fichier de balance.');
        }

        // Retire un éventuel BOM UTF-8 et gère les fichiers exportés en Windows-1252.
        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu);
        if (!mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }

        $premiereLigne = strtok($contenu, "\r\n");
        $delimiteur = substr_count($premiereLigne, ';') >= substr_count($premiereLigne, ',') ? ';' : ',';

        $grille = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $contenu);
        rewind($handle);

        while (($ligne = fgetcsv($handle, 0, $delimiteur)) !== false) {
            $grille[] = $ligne;
        }
        fclose($handle);

        return $grille;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function lireTableur(string $cheminFichier): array
    {
        try {
            $feuille = IOFactory::load($cheminFichier)->getActiveSheet();
        } catch (\Throwable $e) {
            throw new \RuntimeException('Impossible de lire le fichier de balance : ' . $e->getMessage(), previous: $e);
        }

        return $feuille->toArray(null, true, true, false);
    }

    /**
     * @param array<int, string> $enTete
     * @return array<string, int>
     */
    private function resoudreColonnes(array $enTete): array
    {
        $enTeteNormalise = array_map($this->normaliserEnTete(...), $enTete);

        $index = [];
        foreach (self::REGLES_COLONNES as $cle => $regle) {
            $trouve = null;

            foreach ($enTeteNormalise as $position => $texte) {
                if ($texte === '' || in_array($position, $index, true)) {
                    continue;
                }

                if ($this->enTeteCorrespond($texte, $regle)) {
                    $trouve = $position;
                    break;
                }
            }

            if ($trouve === null) {
                throw new \RuntimeException(sprintf(
                    'Colonne attendue introuvable dans le fichier de balance : "%s".',
                    self::LIBELLES_COLONNES[$cle],
                ));
            }

            $index[$cle] = $trouve;
        }

        return $index;
    }

    /**
     * @param array{requiert: array<int, array<int, string>>, exclut: array<int, string>} $regle
     */
    private function enTeteCorrespond(string $texteNormalise, array $regle): bool
    {
        foreach ($regle['exclut'] as $motExclu) {
            if (str_contains($texteNormalise, $motExclu)) {
                return false;
            }
        }

        foreach ($regle['requiert'] as $groupeMotsCles) {
            $groupeTrouve = false;
            foreach ($groupeMotsCles as $motCle) {
                if (str_contains($texteNormalise, $motCle)) {
                    $groupeTrouve = true;
                    break;
                }
            }

            if (!$groupeTrouve) {
                return false;
            }
        }

        return true;
    }

    private function normaliserEnTete(mixed $valeur): string
    {
        $valeur = strtolower(trim((string) $valeur));
        $valeur = str_replace(["\xc2\xa0", "'", "’"], [' ', '', ''], $valeur);
        $valeur = $this->retirerAccents($valeur);

        return $valeur;
    }

    private function retirerAccents(string $texte): string
    {
        $correspondances = [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ];

        return strtr($texte, $correspondances);
    }

    private function normaliserNombre(mixed $valeur): float
    {
        if (is_int($valeur) || is_float($valeur)) {
            return (float) $valeur;
        }

        $texte = trim((string) $valeur);
        if ($texte === '' || $texte === '-') {
            return 0.0;
        }

        $negatif = false;
        if (str_starts_with($texte, '(') && str_ends_with($texte, ')')) {
            $negatif = true;
            $texte = substr($texte, 1, -1);
        }

        // Retire les espaces (classiques et insécables) utilisés comme séparateurs de milliers.
        $texte = str_replace(["\xc2\xa0", ' '], '', $texte);

        $dernierePosVirgule = strrpos($texte, ',');
        $dernierePosPoint = strrpos($texte, '.');

        if ($dernierePosVirgule !== false && $dernierePosPoint !== false) {
            // Les deux séparateurs sont présents : celui qui apparaît en dernier est le séparateur décimal.
            if ($dernierePosVirgule > $dernierePosPoint) {
                $texte = str_replace('.', '', $texte);
                $texte = str_replace(',', '.', $texte);
            } else {
                $texte = str_replace(',', '', $texte);
            }
        } elseif ($dernierePosVirgule !== false) {
            $texte = str_replace(',', '.', $texte);
        }

        $valeurFloat = is_numeric($texte) ? (float) $texte : 0.0;

        return $negatif ? -$valeurFloat : $valeurFloat;
    }
}

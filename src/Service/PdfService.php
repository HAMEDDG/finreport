<?php

namespace App\Service;

use App\Entity\Report;
use App\Repository\AppSettingsRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Génère le PDF d'un rapport financier à partir du template
 * templates/pdf/report.html.twig, converti via Dompdf.
 */
class PdfService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly AppSettingsRepository $appSettingsRepository,
        private readonly string $logosDirectory,
    ) {
    }

    public function genererPdf(Report $report): string
    {
        $settings = $this->appSettingsRepository->getSettings();

        $html = $this->twig->render('pdf/report.html.twig', [
            'report' => $report,
            'devise' => $settings->getDevise(),
            'logoBase64' => $this->logoEnBase64($settings->getLogoEntreprise()),
            'lignesBilan' => $this->apparierLignes($report->getBilanActif(), $report->getBilanPassif()),
            'lignesResultat' => $this->apparierLignes($report->getCompteCharges(), $report->getCompteProduits()),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Encode le logo en data URI pour l'incorporer directement dans le HTML :
     * Dompdf est configuré sans accès réseau (isRemoteEnabled: false), une
     * simple URL vers /uploads/logos/... ne serait donc pas chargée.
     */
    private function logoEnBase64(?string $nomFichier): ?string
    {
        if ($nomFichier === null) {
            return null;
        }

        $chemin = $this->logosDirectory . '/' . $nomFichier;
        if (!is_file($chemin)) {
            return null;
        }

        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
        ];
        $extension = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
        $mime = $mimeTypes[$extension] ?? 'application/octet-stream';

        return sprintf('data:%s;base64,%s', $mime, base64_encode(file_get_contents($chemin)));
    }

    /**
     * Met en correspondance deux rubriques (ex: Actif/Passif) ligne par ligne,
     * afin de les afficher côte à côte dans une seule table à plat (Dompdf gère
     * mal les tables imbriquées avec des largeurs fixes).
     *
     * @return list<array{gaucheLibelle: ?string, gaucheMontant: ?float, gaucheTotal: bool, droiteLibelle: ?string, droiteMontant: ?float, droiteTotal: bool}>
     */
    private function apparierLignes(array $gauche, array $droite): array
    {
        $gaucheLibelles = array_keys($gauche);
        $droiteLibelles = array_keys($droite);
        $nombreLignes = max(count($gaucheLibelles), count($droiteLibelles));

        $lignes = [];
        for ($i = 0; $i < $nombreLignes; $i++) {
            $libelleGauche = $gaucheLibelles[$i] ?? null;
            $libelleDroite = $droiteLibelles[$i] ?? null;

            $lignes[] = [
                'gaucheLibelle' => $libelleGauche,
                'gaucheMontant' => $libelleGauche !== null ? $gauche[$libelleGauche] : null,
                'gaucheTotal' => $libelleGauche !== null && str_starts_with($libelleGauche, 'Total'),
                'droiteLibelle' => $libelleDroite,
                'droiteMontant' => $libelleDroite !== null ? $droite[$libelleDroite] : null,
                'droiteTotal' => $libelleDroite !== null && str_starts_with($libelleDroite, 'Total'),
            ];
        }

        return $lignes;
    }
}

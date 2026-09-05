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
            'bilanActif' => $report->getBilanActif(),
            'bilanPassif' => $report->getBilanPassif(),
            'compteResultat' => $report->getCompteResultat(),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        // Paysage : le Bilan Actif/Passif côte à côte compte 8 colonnes, trop serré en portrait.
        $dompdf->setPaper('A4', 'landscape');
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

}

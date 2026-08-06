<?php

namespace App\Controller;

use App\Entity\Report;
use App\Repository\AppSettingsRepository;
use App\Repository\ReportRepository;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin')]
#[IsGranted('ROLE_USER')]
final class ReportController extends AbstractController
{
    #[Route('/rapports', name: 'app_admin_reports')]
    public function index(ReportRepository $reportRepository): Response
    {
        return $this->render('admin/reports.html.twig', [
            'active_page' => 'reports',
            'reports' => $reportRepository->findAllOrderedByDate(),
        ]);
    }

    #[Route('/rapports/{id}', name: 'app_admin_report_show')]
    public function show(Report $report, AppSettingsRepository $appSettingsRepository): Response
    {
        return $this->render('admin/report_show.html.twig', [
            'active_page' => 'reports',
            'report' => $report,
            'settings' => $appSettingsRepository->getSettings(),
        ]);
    }

    #[Route('/rapports/{id}/pdf', name: 'app_admin_report_pdf')]
    public function downloadPdf(Report $report, PdfService $pdfService, SluggerInterface $slugger): Response
    {
        $pdf = $pdfService->genererPdf($report);

        $nomFichier = sprintf(
            'rapport-%s-%s.pdf',
            $slugger->slug($report->getEntreprise())->lower(),
            $report->getExercice(),
        );

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $nomFichier),
        ]);
    }

    #[Route('/rapports/{id}/supprimer', name: 'app_admin_report_delete', methods: ['POST'])]
    public function delete(Report $report, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-report-' . $report->getId(), $request->request->get('_token'))) {
            $entityManager->remove($report);
            $entityManager->flush();
            $this->addFlash('success', 'Le rapport a été supprimé.');
        }

        return $this->redirectToRoute('app_admin_reports');
    }
}

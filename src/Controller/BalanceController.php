<?php

namespace App\Controller;

use App\Entity\Balance;
use App\Form\BalanceType;
use App\Repository\AppSettingsRepository;
use App\Repository\BalanceRepository;
use App\Service\BalanceImportService;
use App\Service\ReportGenerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_USER')]
final class BalanceController extends AbstractController
{
    #[Route('/fichiers', name: 'app_admin_files', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        BalanceRepository $balanceRepository,
        BalanceImportService $importService,
        EntityManagerInterface $entityManager,
        AppSettingsRepository $appSettingsRepository,
    ): Response {
        $balance = new Balance();

        $nomEntrepriseParDefaut = $appSettingsRepository->getSettings()->getNomEntreprise();
        if ($nomEntrepriseParDefaut !== null) {
            $balance->setEntreprise($nomEntrepriseParDefaut);
        }

        $form = $this->createForm(BalanceType::class, $balance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fichier = $form->get('fichier')->getData();

            $importService->importer($balance, $fichier, $this->getUser());

            if (Balance::STATUT_REUSSI === $balance->getStatut()) {
                $this->addFlash('success', 'La balance a été importée avec succès.');
            } else {
                $this->addFlash('error', "Le fichier n'a pas pu être validé. Vérifiez le format et la taille.");
            }

            return $this->redirectToRoute('app_admin_files');
        }

        return $this->render('admin/files.html.twig', [
            'active_page' => 'files',
            'form' => $form,
            'balances' => $balanceRepository->findAllOrderedByDate(),
        ]);
    }

    #[Route('/fichiers/{id}/supprimer', name: 'app_admin_files_delete', methods: ['POST'])]
    public function delete(Balance $balance, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-balance-' . $balance->getId(), $request->request->get('_token'))) {
            $entityManager->remove($balance);
            $entityManager->flush();
            $this->addFlash('success', 'La balance a été supprimée.');
        }

        return $this->redirectToRoute('app_admin_files');
    }

    #[Route('/report/generate/{id}', name: 'app_admin_report_generate', methods: ['POST'])]
    public function generateReport(Balance $balance, ReportGenerationService $reportGenerationService): Response
    {
        try {
            $reportGenerationService->creerDepuisBalance($balance, $this->getUser());

            $this->addFlash('success', sprintf('Rapport généré pour "%s".', $balance->getEntreprise()));
        } catch (\RuntimeException $e) {
            $this->addFlash('error', sprintf('Impossible de générer le rapport pour "%s" : %s', $balance->getEntreprise(), $e->getMessage()));
        }

        return $this->redirectToRoute('app_admin_reports');
    }
}

<?php

namespace App\Controller;

use App\Repository\BalanceRepository;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_USER')]
final class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin')]
    public function index(
        BalanceRepository $balanceRepository,
        ReportRepository $reportRepository,
        UserRepository $userRepository,
    ): Response {
        $repartitionStatuts = $balanceRepository->countParStatut();

        return $this->render('admin/index.html.twig', [
            'active_page' => 'dashboard',
            'totalBalances' => array_sum($repartitionStatuts),
            'totalRapports' => $reportRepository->count([]),
            'totalUtilisateurs' => $this->isGranted('ROLE_ADMIN') ? $userRepository->count([]) : null,
            'balancesEnErreur' => $repartitionStatuts['erreur'],
            'balancesRecentes' => $balanceRepository->findRecent(5),
            'rapportsRecents' => $reportRepository->findRecent(5),
        ]);
    }
}

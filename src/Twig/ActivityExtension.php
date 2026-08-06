<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\BalanceRepository;
use App\Repository\ReportRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Fournit le fil d'activité récente (imports de balances + rapports générés)
 * affiché dans le menu de notifications de l'en-tête admin, ainsi que le
 * nombre d'éléments non encore consultés par l'utilisateur courant.
 *
 * Ce n'est pas un système de notifications persistées : on réutilise les
 * données déjà existantes (Balance, Report) triées par date, et on compare
 * chaque date à User::derniereConsultationNotifications pour savoir ce qui
 * est "non lu".
 */
class ActivityExtension extends AbstractExtension
{
    public function __construct(
        private readonly BalanceRepository $balanceRepository,
        private readonly ReportRepository $reportRepository,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('activite_recente', $this->activiteRecente(...)),
            new TwigFunction('notifications_non_lues_compte', $this->nombreNonLues(...)),
        ];
    }

    /**
     * @return list<array{type: string, titre: string, date: \DateTimeImmutable, statut: string, id: int, nonLu: bool}>
     */
    public function activiteRecente(int $limite = 6): array
    {
        $derniereConsultation = $this->derniereConsultation();
        $elements = [];

        foreach ($this->balanceRepository->findRecent(10) as $balance) {
            $elements[] = [
                'type' => 'balance',
                'titre' => $balance->getEntreprise(),
                'date' => $balance->getDateImportation(),
                'statut' => $balance->getStatut(),
                'id' => $balance->getId(),
            ];
        }

        foreach ($this->reportRepository->findRecent(10) as $report) {
            $elements[] = [
                'type' => 'report',
                'titre' => $report->getEntreprise(),
                'date' => $report->getDateGeneration(),
                'statut' => 'rapport',
                'id' => $report->getId(),
            ];
        }

        usort($elements, fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        $elements = array_slice($elements, 0, $limite);

        foreach ($elements as &$element) {
            $element['nonLu'] = $derniereConsultation === null || $element['date'] > $derniereConsultation;
        }

        return $elements;
    }

    public function nombreNonLues(): int
    {
        $derniereConsultation = $this->derniereConsultation();

        // Personne connecté sans notion de "consultation" (ne devrait pas arriver
        // pour un utilisateur authentifié, mais on reste défensif).
        if ($derniereConsultation === null) {
            return count($this->activiteRecente(10));
        }

        $nombre = 0;
        foreach ($this->activiteRecente(20) as $element) {
            if ($element['date'] > $derniereConsultation) {
                $nombre++;
            }
        }

        return $nombre;
    }

    private function derniereConsultation(): ?\DateTimeImmutable
    {
        $utilisateur = $this->security->getUser();

        return $utilisateur instanceof User ? $utilisateur->getDerniereConsultationNotifications() : null;
    }
}

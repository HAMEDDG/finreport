<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    #[Route('/notifications/marquer-lu', name: 'app_admin_notifications_read', methods: ['POST'])]
    public function marquerLu(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->isCsrfTokenValid('notifications-marquer-lu', $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['erreur' => 'Jeton CSRF invalide.'], 400);
        }

        $utilisateur = $this->getUser();
        if ($utilisateur instanceof User) {
            $utilisateur->setDerniereConsultationNotifications(new \DateTimeImmutable());
            $entityManager->flush();
        }

        return new JsonResponse(['ok' => true]);
    }
}

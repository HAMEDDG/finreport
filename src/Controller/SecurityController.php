<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home_redirect');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Cette méthode ne doit jamais être appelée directement, elle est interceptée par la configuration du firewall "logout" dans security.yaml.');
    }

    #[Route(path: '/', name: 'app_home_redirect')]
    public function homeRedirect(): Response
    {
       

        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('app_admin');
        }

        return $this->redirectToRoute('app_login');
    }
}
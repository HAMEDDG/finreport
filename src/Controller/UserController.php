<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    #[Route('/utilisateurs', name: 'app_admin_users')]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('admin/users.html.twig', [
            'active_page' => 'users',
            'users' => $userRepository->findAll(),
            'form' => $this->createForm(UserType::class, new User())->createView(),
        ]);
    }

    #[Route('/utilisateurs/nouveau', name: 'app_admin_user_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles([$form->get('roleChoisi')->getData()]);
            $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès.');
        } else {
            $this->addFlash('error', "Impossible de créer l'utilisateur — vérifiez les champs.");
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/utilisateurs/{id}/modifier', name: 'app_admin_user_edit', methods: ['POST'])]
    public function edit(User $user, Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles([$form->get('roleChoisi')->getData()]);

            $nouveauMotDePasse = $form->get('plainPassword')->getData();
            if (!empty($nouveauMotDePasse)) {
                $user->setPassword($hasher->hashPassword($user, $nouveauMotDePasse));
            }

            $entityManager->flush();
            $this->addFlash('success', 'Utilisateur modifié avec succès.');
        } else {
            $this->addFlash('error', "Impossible de modifier l'utilisateur.");
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/utilisateurs/{id}/supprimer', name: 'app_admin_user_delete', methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-user-' . $user->getId(), $request->request->get('_token'))) {
            if ($user === $this->getUser()) {
                $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            } else {
                $entityManager->remove($user);
                $entityManager->flush();
                $this->addFlash('success', 'Utilisateur supprimé.');
            }
        }

        return $this->redirectToRoute('app_admin_users');
    }
}

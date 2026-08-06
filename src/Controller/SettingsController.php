<?php

namespace App\Controller;

use App\Form\AppSettingsType;
use App\Form\ChangePasswordType;
use App\Repository\AppSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin')]
#[IsGranted('ROLE_USER')]
final class SettingsController extends AbstractController
{
    public function __construct(private readonly string $logosDirectory)
    {
    }

    #[Route('/parametres', name: 'app_admin_settings')]
    public function index(AppSettingsRepository $appSettingsRepository): Response
    {
        $passwordForm = $this->createForm(ChangePasswordType::class);
        $appSettingsForm = null;

        if ($this->isGranted('ROLE_ADMIN')) {
            $appSettingsForm = $this->createForm(AppSettingsType::class, $appSettingsRepository->getSettings());
        }

        return $this->render('admin/settings.html.twig', [
            'active_page' => 'settings',
            'passwordForm' => $passwordForm,
            'appSettingsForm' => $appSettingsForm?->createView(),
        ]);
    }

    #[Route('/parametres/mot-de-passe', name: 'app_admin_settings_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();

            if (!$hasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');

                return $this->redirectToRoute('app_admin_settings');
            }

            $nouveauMotDePasse = $form->get('newPassword')->getData();
            $user->setPassword($hasher->hashPassword($user, $nouveauMotDePasse));
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');
        } else {
            $this->addFlash('error', 'Impossible de modifier le mot de passe — vérifiez les champs.');
        }

        return $this->redirectToRoute('app_admin_settings');
    }

    #[Route('/parametres/entreprise', name: 'app_admin_settings_app', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateAppSettings(
        Request $request,
        AppSettingsRepository $appSettingsRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
    ): Response {
        $settings = $appSettingsRepository->getSettings();
        $form = $this->createForm(AppSettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $logo */
            $logo = $form->get('logo')->getData();

            if ($logo !== null) {
                $nomOriginal = pathinfo($logo->getClientOriginalName(), PATHINFO_FILENAME);
                $nomSecurise = $slugger->slug($nomOriginal)->lower();
                $extension = strtolower($logo->getClientOriginalExtension());
                $nomFichierFinal = sprintf('%s-%s.%s', $nomSecurise, uniqid(), $extension);

                $logo->move($this->logosDirectory, $nomFichierFinal);
                $settings->setLogoEntreprise($nomFichierFinal);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Les paramètres ont été mis à jour avec succès.');
        } else {
            $this->addFlash('error', 'Impossible de mettre à jour les paramètres — vérifiez les champs.');
        }

        return $this->redirectToRoute('app_admin_settings');
    }
}

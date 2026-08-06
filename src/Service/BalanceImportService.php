<?php

namespace App\Service;

use App\Entity\Balance;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Gère l'enregistrement physique d'une balance et la création de son
 * entité en base. Le calcul comptable (lecture des comptes, classement
 * SYSCOHADA, montants du Bilan et du Compte de Résultat) est effectué
 * séparément par ReportGenerationService lors de la génération du rapport.
 */
class BalanceImportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BalanceValidationService $validationService,
        private readonly SluggerInterface $slugger,
        private readonly string $balancesDirectory,
    ) {
    }

    public function importer(
        Balance $balance,
        UploadedFile $fichier,
        User $utilisateur,
    ): Balance {
        $erreurs = $this->validationService->valider($fichier);

        $nomOriginal = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
        $nomSecurise = $this->slugger->slug($nomOriginal)->lower();
        $extension = strtolower($fichier->getClientOriginalExtension());
        $nomFichierFinal = sprintf('%s-%s.%s', $nomSecurise, uniqid(), $extension);

        // La taille et le nombre de lignes doivent être lus AVANT le déplacement du
        // fichier : une fois déplacé, l'UploadedFile ne pointe plus vers un fichier existant.
        $tailleOctets = $fichier->getSize();
        $nombreLignes = $this->validationService->compterLignes($fichier);

        if (empty($erreurs)) {
            $fichier->move($this->balancesDirectory, $nomFichierFinal);
        }

        $balance->setNomFichier($nomFichierFinal);
        $balance->setExtension($extension);
        $balance->setTailleOctets($tailleOctets);
        $balance->setNombreLignes($nombreLignes);
        $balance->setUtilisateur($utilisateur);
        $balance->setStatut(empty($erreurs) ? Balance::STATUT_REUSSI : Balance::STATUT_ERREUR);

        $this->entityManager->persist($balance);
        $this->entityManager->flush();

        return $balance;
    }
}

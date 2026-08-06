<?php

namespace App\Entity;

use App\Repository\BalanceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BalanceRepository::class)]
class Balance
{
    public const STATUT_REUSSI = 'reussi';
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_ERREUR = 'erreur';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $entreprise = null;

    #[ORM\Column(length: 4)]
    private ?string $exercice = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $nomFichier = null;

    #[ORM\Column(length: 10)]
    private ?string $extension = null;

    #[ORM\Column]
    private ?int $tailleOctets = null;

    #[ORM\Column(nullable: true)]
    private ?int $nombreLignes = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateImportation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $utilisateur = null;

    public function __construct()
    {
        $this->dateImportation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntreprise(): ?string
    {
        return $this->entreprise;
    }

    public function setEntreprise(string $entreprise): static
    {
        $this->entreprise = $entreprise;

        return $this;
    }

    public function getExercice(): ?string
    {
        return $this->exercice;
    }

    public function setExercice(string $exercice): static
    {
        $this->exercice = $exercice;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getNomFichier(): ?string
    {
        return $this->nomFichier;
    }

    public function setNomFichier(string $nomFichier): static
    {
        $this->nomFichier = $nomFichier;

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): static
    {
        $this->extension = $extension;

        return $this;
    }

    public function getTailleOctets(): ?int
    {
        return $this->tailleOctets;
    }

    public function setTailleOctets(int $tailleOctets): static
    {
        $this->tailleOctets = $tailleOctets;

        return $this;
    }

    public function getTailleFormatee(): string
    {
        $taille = $this->tailleOctets ?? 0;

        if ($taille >= 1024 * 1024) {
            return round($taille / (1024 * 1024), 2) . ' Mo';
        }

        return round($taille / 1024, 1) . ' Ko';
    }

    public function getNombreLignes(): ?int
    {
        return $this->nombreLignes;
    }

    public function setNombreLignes(?int $nombreLignes): static
    {
        $this->nombreLignes = $nombreLignes;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDateImportation(): ?\DateTimeImmutable
    {
        return $this->dateImportation;
    }

    public function setDateImportation(\DateTimeImmutable $dateImportation): static
    {
        $this->dateImportation = $dateImportation;

        return $this;
    }

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?User $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }
}
<?php

namespace App\Entity;

use App\Repository\ReportRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
class Report
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $entreprise = null;

    #[ORM\Column(length: 4)]
    private ?string $exercice = null;

    #[ORM\Column(length: 100)]
    private string $type = 'Rapport financier personnalisé';

    #[ORM\Column(length: 20)]
    private string $statut = 'disponible';

    #[ORM\Column]
    private ?\DateTimeImmutable $dateGeneration = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $utilisateur = null;

    #[ORM\ManyToOne(targetEntity: Balance::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Balance $balance = null;

    /** Rubriques du Bilan — Actif. Structure prête, montants à 0 en attendant le moteur de calcul. */
    #[ORM\Column(type: 'json')]
    private array $bilanActif = [];

    /** Rubriques du Bilan — Passif. */
    #[ORM\Column(type: 'json')]
    private array $bilanPassif = [];

    /** Rubriques du Compte de Résultat — Charges. */
    #[ORM\Column(type: 'json')]
    private array $compteCharges = [];

    /** Rubriques du Compte de Résultat — Produits. */
    #[ORM\Column(type: 'json')]
    private array $compteProduits = [];

    public function __construct()
    {
        $this->dateGeneration = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEntreprise(): ?string { return $this->entreprise; }
    public function setEntreprise(string $entreprise): static { $this->entreprise = $entreprise; return $this; }

    public function getExercice(): ?string { return $this->exercice; }
    public function setExercice(string $exercice): static { $this->exercice = $exercice; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getDateGeneration(): ?\DateTimeImmutable { return $this->dateGeneration; }
    public function setDateGeneration(\DateTimeImmutable $dateGeneration): static { $this->dateGeneration = $dateGeneration; return $this; }

    public function getUtilisateur(): ?User { return $this->utilisateur; }
    public function setUtilisateur(?User $utilisateur): static { $this->utilisateur = $utilisateur; return $this; }

    public function getBalance(): ?Balance { return $this->balance; }
    public function setBalance(?Balance $balance): static { $this->balance = $balance; return $this; }

    public function getBilanActif(): array { return $this->bilanActif; }
    public function setBilanActif(array $bilanActif): static { $this->bilanActif = $bilanActif; return $this; }

    public function getBilanPassif(): array { return $this->bilanPassif; }
    public function setBilanPassif(array $bilanPassif): static { $this->bilanPassif = $bilanPassif; return $this; }

    public function getCompteCharges(): array { return $this->compteCharges; }
    public function setCompteCharges(array $compteCharges): static { $this->compteCharges = $compteCharges; return $this; }

    public function getCompteProduits(): array { return $this->compteProduits; }
    public function setCompteProduits(array $compteProduits): static { $this->compteProduits = $compteProduits; return $this; }
}

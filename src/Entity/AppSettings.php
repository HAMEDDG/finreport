<?php

namespace App\Entity;

use App\Repository\AppSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Paramètres globaux de l'application. Une seule ligne existe en base
 * (voir AppSettingsRepository::getSettings()) : ce ne sont pas des
 * préférences par utilisateur, mais une configuration partagée par tous.
 */
#[ORM\Entity(repositoryClass: AppSettingsRepository::class)]
class AppSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $nomEntreprise = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoEntreprise = null;

    #[ORM\Column(length: 10)]
    private string $devise = 'FCFA';

    #[ORM\Column]
    private float $toleranceEquilibre = 1.0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomEntreprise(): ?string
    {
        return $this->nomEntreprise;
    }

    public function setNomEntreprise(?string $nomEntreprise): static
    {
        $this->nomEntreprise = $nomEntreprise;

        return $this;
    }

    public function getLogoEntreprise(): ?string
    {
        return $this->logoEntreprise;
    }

    public function setLogoEntreprise(?string $logoEntreprise): static
    {
        $this->logoEntreprise = $logoEntreprise;

        return $this;
    }

    public function getDevise(): string
    {
        return $this->devise;
    }

    public function setDevise(string $devise): static
    {
        $this->devise = $devise;

        return $this;
    }

    public function getToleranceEquilibre(): float
    {
        return $this->toleranceEquilibre;
    }

    public function setToleranceEquilibre(float $toleranceEquilibre): static
    {
        $this->toleranceEquilibre = $toleranceEquilibre;

        return $this;
    }
}

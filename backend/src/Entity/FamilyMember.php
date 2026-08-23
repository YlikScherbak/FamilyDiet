<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FamilyMemberRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FamilyMemberRepository::class)]
class FamilyMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column]
    #[Assert\Positive]
    private int $kcalTarget = 2000;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $proteinTarget = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $fatTarget = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $carbsTarget = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getKcalTarget(): int
    {
        return $this->kcalTarget;
    }

    public function setKcalTarget(int $kcalTarget): static
    {
        $this->kcalTarget = $kcalTarget;

        return $this;
    }

    public function getProteinTarget(): ?int
    {
        return $this->proteinTarget;
    }

    public function setProteinTarget(?int $proteinTarget): static
    {
        $this->proteinTarget = $proteinTarget;

        return $this;
    }

    public function getFatTarget(): ?int
    {
        return $this->fatTarget;
    }

    public function setFatTarget(?int $fatTarget): static
    {
        $this->fatTarget = $fatTarget;

        return $this;
    }

    public function getCarbsTarget(): ?int
    {
        return $this->carbsTarget;
    }

    public function setCarbsTarget(?int $carbsTarget): static
    {
        $this->carbsTarget = $carbsTarget;

        return $this;
    }
}

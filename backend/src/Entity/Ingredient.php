<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\IngredientCategory;
use App\Enum\Unit;
use App\Repository\IngredientRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: IngredientRepository::class)]
class Ingredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 300, unique: true)]
    #[Assert\NotBlank]
    private string $name = '';

    /** Оригінальна назва USDA (англійською) — для пошуку та звірки; null для власних інгредієнтів. */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $nameEn = null;

    #[ORM\Column(length: 30, enumType: IngredientCategory::class)]
    private IngredientCategory $category = IngredientCategory::Other;

    #[ORM\Column(length: 10, enumType: Unit::class)]
    private Unit $unit = Unit::Grams;

    /** Ккал на 100 г/мл (для штучних — на 100 г, перерахунок через pieceWeightGrams). */
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private float $kcalPer100 = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private float $proteinPer100 = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private float $fatPer100 = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private float $carbsPer100 = 0;

    /** Вага 1 шт у грамах — обов'язкова для інгредієнтів з одиницею "шт". */
    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?float $pieceWeightGrams = null;

    /** ID продукту в USDA FoodData Central — ключ ідемпотентного імпорту. */
    #[ORM\Column(nullable: true, unique: true)]
    private ?int $fdcId = null;

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

    public function getCategory(): IngredientCategory
    {
        return $this->category;
    }

    public function setCategory(IngredientCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getUnit(): Unit
    {
        return $this->unit;
    }

    public function setUnit(Unit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getKcalPer100(): float
    {
        return $this->kcalPer100;
    }

    public function setKcalPer100(float $kcalPer100): static
    {
        $this->kcalPer100 = $kcalPer100;

        return $this;
    }

    public function getProteinPer100(): float
    {
        return $this->proteinPer100;
    }

    public function setProteinPer100(float $proteinPer100): static
    {
        $this->proteinPer100 = $proteinPer100;

        return $this;
    }

    public function getFatPer100(): float
    {
        return $this->fatPer100;
    }

    public function setFatPer100(float $fatPer100): static
    {
        $this->fatPer100 = $fatPer100;

        return $this;
    }

    public function getCarbsPer100(): float
    {
        return $this->carbsPer100;
    }

    public function setCarbsPer100(float $carbsPer100): static
    {
        $this->carbsPer100 = $carbsPer100;

        return $this;
    }

    public function getPieceWeightGrams(): ?float
    {
        return $this->pieceWeightGrams;
    }

    public function setPieceWeightGrams(?float $pieceWeightGrams): static
    {
        $this->pieceWeightGrams = $pieceWeightGrams;

        return $this;
    }

    public function getNameEn(): ?string
    {
        return $this->nameEn;
    }

    public function setNameEn(?string $nameEn): static
    {
        $this->nameEn = $nameEn;

        return $this;
    }

    public function getFdcId(): ?int
    {
        return $this->fdcId;
    }

    public function setFdcId(?int $fdcId): static
    {
        $this->fdcId = $fdcId;

        return $this;
    }

    /**
     * Кількість у грамах для розрахунку КБЖВ: amount у одиниці інгредієнта.
     */
    public function toGrams(float $amount): float
    {
        return match ($this->unit) {
            Unit::Pieces => $amount * ($this->pieceWeightGrams ?? 0),
            default => $amount,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MealType;
use App\Repository\MealPlanEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MealPlanEntryRepository::class)]
#[ORM\Index(name: 'idx_meal_plan_date', columns: ['date'])]
class MealPlanEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    #[ORM\ManyToOne(targetEntity: FamilyMember::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?FamilyMember $familyMember = null;

    #[ORM\Column(length: 20, enumType: MealType::class)]
    private MealType $slot = MealType::Lunch;

    /** Запис — АБО страва, АБО продукт із кількістю. Рівно одне з двох. */
    #[ORM\ManyToOne(targetEntity: Dish::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Dish $dish = null;

    #[ORM\ManyToOne(targetEntity: Ingredient::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Ingredient $ingredient = null;

    /** Кількість у одиниці інгредієнта (для записів-продуктів). */
    #[ORM\Column(nullable: true)]
    private ?float $amount = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getFamilyMember(): ?FamilyMember
    {
        return $this->familyMember;
    }

    public function setFamilyMember(?FamilyMember $familyMember): static
    {
        $this->familyMember = $familyMember;

        return $this;
    }

    public function getSlot(): MealType
    {
        return $this->slot;
    }

    public function setSlot(MealType $slot): static
    {
        $this->slot = $slot;

        return $this;
    }

    public function getDish(): ?Dish
    {
        return $this->dish;
    }

    public function setDish(?Dish $dish): static
    {
        $this->dish = $dish;

        return $this;
    }

    public function getIngredient(): ?Ingredient
    {
        return $this->ingredient;
    }

    public function setIngredient(?Ingredient $ingredient): static
    {
        $this->ingredient = $ingredient;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(?float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    /** @return array{kcal: float, protein: float, fat: float, carbs: float}|null */
    public function calculateNutrition(): ?array
    {
        if ($this->ingredient !== null && $this->amount !== null) {
            $grams = $this->ingredient->toGrams($this->amount);

            return [
                'kcal' => round($grams * $this->ingredient->getKcalPer100() / 100, 1),
                'protein' => round($grams * $this->ingredient->getProteinPer100() / 100, 1),
                'fat' => round($grams * $this->ingredient->getFatPer100() / 100, 1),
                'carbs' => round($grams * $this->ingredient->getCarbsPer100() / 100, 1),
            ];
        }

        if ($this->dish !== null && $this->familyMember !== null) {
            return $this->dish->getPortionFor($this->familyMember)?->calculateNutrition();
        }

        return null;
    }
}

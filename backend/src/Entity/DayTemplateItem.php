<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MealType;
use Doctrine\ORM\Mapping as ORM;

/**
 * Один запис шаблону: людина + слот + (страва АБО продукт із кількістю).
 * Видалення страви/інгредієнта/члена сім'ї каскадно прибирає запис із шаблону —
 * шаблон легкий пресет і не блокує керування довідниками (рішення користувача).
 */
#[ORM\Entity]
class DayTemplateItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DayTemplate::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DayTemplate $template = null;

    #[ORM\ManyToOne(targetEntity: FamilyMember::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?FamilyMember $familyMember = null;

    #[ORM\Column(length: 20, enumType: MealType::class)]
    private MealType $slot = MealType::Lunch;

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

    public function getTemplate(): ?DayTemplate
    {
        return $this->template;
    }

    public function setTemplate(?DayTemplate $template): static
    {
        $this->template = $template;

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
}

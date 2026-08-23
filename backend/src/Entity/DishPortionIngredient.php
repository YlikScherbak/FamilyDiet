<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_portion_ingredient', columns: ['portion_id', 'ingredient_id'])]
class DishPortionIngredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DishPortion::class, inversedBy: 'ingredients')]
    #[ORM\JoinColumn(name: 'portion_id', nullable: false, onDelete: 'CASCADE')]
    private ?DishPortion $portion = null;

    #[ORM\ManyToOne(targetEntity: Ingredient::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ingredient $ingredient = null;

    /** Кількість у одиниці інгредієнта (г/мл/шт). */
    #[ORM\Column]
    #[Assert\Positive]
    private float $amount = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPortion(): ?DishPortion
    {
        return $this->portion;
    }

    public function setPortion(?DishPortion $portion): static
    {
        $this->portion = $portion;

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

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }
}

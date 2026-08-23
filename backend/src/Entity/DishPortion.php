<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DishPortionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DishPortionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_dish_member', columns: ['dish_id', 'family_member_id'])]
class DishPortion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Dish::class, inversedBy: 'portions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Dish $dish = null;

    #[ORM\ManyToOne(targetEntity: FamilyMember::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?FamilyMember $familyMember = null;

    /** @var Collection<int, DishPortionIngredient> */
    #[ORM\OneToMany(targetEntity: DishPortionIngredient::class, mappedBy: 'portion', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $ingredients;

    public function __construct()
    {
        $this->ingredients = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFamilyMember(): ?FamilyMember
    {
        return $this->familyMember;
    }

    public function setFamilyMember(?FamilyMember $familyMember): static
    {
        $this->familyMember = $familyMember;

        return $this;
    }

    /** @return Collection<int, DishPortionIngredient> */
    public function getIngredients(): Collection
    {
        return $this->ingredients;
    }

    public function addIngredient(DishPortionIngredient $ingredient): static
    {
        if (!$this->ingredients->contains($ingredient)) {
            $this->ingredients->add($ingredient);
            $ingredient->setPortion($this);
        }

        return $this;
    }

    public function removeIngredient(DishPortionIngredient $ingredient): static
    {
        $this->ingredients->removeElement($ingredient);

        return $this;
    }

    /** @return array{kcal: float, protein: float, fat: float, carbs: float} */
    public function calculateNutrition(): array
    {
        $totals = ['kcal' => 0.0, 'protein' => 0.0, 'fat' => 0.0, 'carbs' => 0.0];

        foreach ($this->ingredients as $item) {
            $ingredient = $item->getIngredient();
            if ($ingredient === null) {
                continue;
            }

            $grams = $ingredient->toGrams($item->getAmount());
            $totals['kcal'] += $grams * $ingredient->getKcalPer100() / 100;
            $totals['protein'] += $grams * $ingredient->getProteinPer100() / 100;
            $totals['fat'] += $grams * $ingredient->getFatPer100() / 100;
            $totals['carbs'] += $grams * $ingredient->getCarbsPer100() / 100;
        }

        return array_map(static fn (float $v): float => round($v, 1), $totals);
    }
}

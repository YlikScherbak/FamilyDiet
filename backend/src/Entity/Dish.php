<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MealType;
use App\Repository\DishRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DishRepository::class)]
class Dish
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Код із плану (B01, L07...), для власних страв — null. */
    #[ORM\Column(length: 10, unique: true, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 20, enumType: MealType::class)]
    private MealType $category = MealType::Lunch;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $recipe = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(requireTld: true)]
    private ?string $youtubeUrl = null;

    #[ORM\Column]
    private bool $batchCooking = false;

    /** @var Collection<int, DishPortion> */
    #[ORM\OneToMany(targetEntity: DishPortion::class, mappedBy: 'dish', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $portions;

    public function __construct()
    {
        $this->portions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
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

    public function getCategory(): MealType
    {
        return $this->category;
    }

    public function setCategory(MealType $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getRecipe(): ?string
    {
        return $this->recipe;
    }

    public function setRecipe(?string $recipe): static
    {
        $this->recipe = $recipe;

        return $this;
    }

    public function getYoutubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function setYoutubeUrl(?string $youtubeUrl): static
    {
        $this->youtubeUrl = $youtubeUrl;

        return $this;
    }

    public function isBatchCooking(): bool
    {
        return $this->batchCooking;
    }

    public function setBatchCooking(bool $batchCooking): static
    {
        $this->batchCooking = $batchCooking;

        return $this;
    }

    /** @return Collection<int, DishPortion> */
    public function getPortions(): Collection
    {
        return $this->portions;
    }

    public function addPortion(DishPortion $portion): static
    {
        if (!$this->portions->contains($portion)) {
            $this->portions->add($portion);
            $portion->setDish($this);
        }

        return $this;
    }

    public function removePortion(DishPortion $portion): static
    {
        $this->portions->removeElement($portion);

        return $this;
    }

    public function getPortionFor(FamilyMember $member): ?DishPortion
    {
        foreach ($this->portions as $portion) {
            if ($portion->getFamilyMember()?->getId() === $member->getId()) {
                return $portion;
            }
        }

        return null;
    }
}

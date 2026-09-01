<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DayTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Іменований знімок цілого дня меню на всю сім'ю. Застосування шаблону
 * транзакційно замінює всі записи обраної дати (як PUT /meal-plan/day).
 */
#[ORM\Entity(repositoryClass: DayTemplateRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_day_template_name', columns: ['name'])]
class DayTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, DayTemplateItem> */
    #[ORM\OneToMany(targetEntity: DayTemplateItem::class, mappedBy: 'template', cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, DayTemplateItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(DayTemplateItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setTemplate($this);
        }

        return $this;
    }
}

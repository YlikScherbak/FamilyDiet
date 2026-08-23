<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\HealthEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Подія журналу здоров'я: замір тиску, головний біль, ліки, довільна нотатка.
 * Структуровані дані типу (САТ/ДАТ/пульс, тяжкість, назва власної події) — у payload (JSONB);
 * набір і валідація полів — у HealthEventTypeRegistry.
 */
#[ORM\Entity(repositoryClass: HealthEventRepository::class)]
#[ORM\Index(name: 'idx_health_event_date', columns: ['date'])]
class HealthEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FamilyMember::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?FamilyMember $familyMember = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    /** Час події (ГГ:ХХ); може бути відсутній для подій «протягом дня». */
    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $time = null;

    #[ORM\Column(length: 40)]
    private string $type = 'note';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $payload = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getTime(): ?\DateTimeImmutable
    {
        return $this->time;
    }

    public function setTime(?\DateTimeImmutable $time): static
    {
        $this->time = $time;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @param array<string, mixed> $payload */
    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }
}

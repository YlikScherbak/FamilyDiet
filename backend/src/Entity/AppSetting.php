<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AppSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Довільне іменоване налаштування застосунку (JSONB). Ключ — рядок виду
 * health_chart; значення — обʼєкт, структуру якого визначає споживач.
 */
#[ORM\Entity(repositoryClass: AppSettingRepository::class)]
class AppSetting
{
    #[ORM\Id]
    #[ORM\Column(length: 50)]
    private string $key = '';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $value = [];

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getValue(): array
    {
        return $this->value;
    }

    /** @param array<string, mixed> $value */
    public function setValue(array $value): static
    {
        $this->value = $value;

        return $this;
    }
}

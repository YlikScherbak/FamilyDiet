<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Dish;
use App\Entity\DishPortion;
use App\Entity\DishPortionIngredient;
use App\Entity\FamilyMember;
use App\Entity\Ingredient;
use App\Enum\IngredientCategory;
use App\Enum\MealType;
use App\Enum\Unit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $server
     */
    protected function request(string $method, string $uri, array $payload = [], array $server = []): void
    {
        $this->client->jsonRequest($method, $uri, $payload, $server);
    }

    /** @return array<mixed> */
    protected function responseJson(): array
    {
        $content = (string) $this->client->getResponse()->getContent();

        return (array) json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    protected function assertStatus(int $expected): void
    {
        self::assertSame(
            $expected,
            $this->client->getResponse()->getStatusCode(),
            (string) $this->client->getResponse()->getContent()
        );
    }

    protected function createMember(string $name, int $kcalTarget = 2000): FamilyMember
    {
        $member = (new FamilyMember())->setName($name)->setKcalTarget($kcalTarget);
        $this->em->persist($member);
        $this->em->flush();

        return $member;
    }

    protected function createIngredient(string $name, float $kcalPer100 = 100.0): Ingredient
    {
        $ingredient = (new Ingredient())
            ->setName($name)
            ->setCategory(IngredientCategory::Other)
            ->setUnit(Unit::Grams)
            ->setKcalPer100($kcalPer100)
            ->setProteinPer100(10.0)
            ->setFatPer100(5.0)
            ->setCarbsPer100(20.0);
        $this->em->persist($ingredient);
        $this->em->flush();

        return $ingredient;
    }

    /** Страва з однією порцією для $member: $grams грамів $ingredient. */
    protected function createDish(string $name, FamilyMember $member, Ingredient $ingredient, float $grams = 100.0): Dish
    {
        $dish = (new Dish())
            ->setName($name)
            ->setCategory(MealType::Lunch)
            ->addPortion(
                (new DishPortion())
                    ->setFamilyMember($member)
                    ->addIngredient((new DishPortionIngredient())->setIngredient($ingredient)->setAmount($grams))
            );
        $this->em->persist($dish);
        $this->em->flush();

        return $dish;
    }

    /** @return array<int, array<string, mixed>> Записи меню за день із GET /api/meal-plan. */
    protected function entriesOn(string $date): array
    {
        $this->request('GET', sprintf('/api/meal-plan?from=%s&to=%s', $date, $date));
        $this->assertStatus(200);
        /** @var array{entries: array<int, array<string, mixed>>} $data */
        $data = $this->responseJson();

        return $data['entries'];
    }
}

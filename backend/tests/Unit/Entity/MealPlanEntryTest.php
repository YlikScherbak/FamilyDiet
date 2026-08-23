<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Dish;
use App\Entity\DishPortion;
use App\Entity\DishPortionIngredient;
use App\Entity\FamilyMember;
use App\Entity\Ingredient;
use App\Entity\MealPlanEntry;
use App\Enum\Unit;
use PHPUnit\Framework\TestCase;

final class MealPlanEntryTest extends TestCase
{
    public function testRawProductNutritionFromGrams(): void
    {
        $buckwheat = (new Ingredient())
            ->setUnit(Unit::Grams)
            ->setKcalPer100(110.0)
            ->setProteinPer100(4.2)
            ->setFatPer100(1.1)
            ->setCarbsPer100(21.3);

        $entry = (new MealPlanEntry())->setIngredient($buckwheat)->setAmount(150.0);

        self::assertSame(
            ['kcal' => 165.0, 'protein' => 6.3, 'fat' => 1.7, 'carbs' => 32.0],
            $entry->calculateNutrition()
        );
    }

    public function testRawPieceProductUsesPieceWeight(): void
    {
        $egg = (new Ingredient())
            ->setUnit(Unit::Pieces)
            ->setPieceWeightGrams(50.0)
            ->setKcalPer100(155.0)
            ->setProteinPer100(13.0)
            ->setFatPer100(11.0)
            ->setCarbsPer100(1.1);

        $entry = (new MealPlanEntry())->setIngredient($egg)->setAmount(3.0);

        // 3 шт × 50 г = 150 г
        self::assertSame(
            ['kcal' => 232.5, 'protein' => 19.5, 'fat' => 16.5, 'carbs' => 1.7],
            $entry->calculateNutrition()
        );
    }

    public function testDishNutritionTakenFromMembersOwnPortion(): void
    {
        $husband = $this->member(1, 'Чоловік', 2250);
        $wife = $this->member(2, 'Жінка', 2100);

        $oats = (new Ingredient())->setUnit(Unit::Grams)->setKcalPer100(100.0);

        $dish = (new Dish())->setName('Вівсянка')
            ->addPortion($this->portion($husband, $oats, 300.0))
            ->addPortion($this->portion($wife, $oats, 200.0));

        $forWife = (new MealPlanEntry())->setDish($dish)->setFamilyMember($wife);

        $nutrition = $forWife->calculateNutrition();

        self::assertNotNull($nutrition);
        self::assertSame(200.0, $nutrition['kcal']);
    }

    public function testDishWithoutPortionForMemberGivesNull(): void
    {
        $husband = $this->member(1, 'Чоловік', 2250);
        $guest = $this->member(3, 'Гість', 2000);

        $dish = (new Dish())->setName('Стейк')
            ->addPortion($this->portion($husband, (new Ingredient())->setKcalPer100(250.0), 200.0));

        $entry = (new MealPlanEntry())->setDish($dish)->setFamilyMember($guest);

        self::assertNull($entry->calculateNutrition());
    }

    public function testEmptyEntryGivesNull(): void
    {
        self::assertNull((new MealPlanEntry())->calculateNutrition());
    }

    /** Dish::getPortionFor зіставляє людей по id, тож без БД проставляємо id напряму. */
    private function member(int $id, string $name, int $kcalTarget): FamilyMember
    {
        $member = (new FamilyMember())->setName($name)->setKcalTarget($kcalTarget);
        $ref = new \ReflectionProperty(FamilyMember::class, 'id');
        $ref->setValue($member, $id);

        return $member;
    }

    private function portion(FamilyMember $member, Ingredient $ingredient, float $amount): DishPortion
    {
        return (new DishPortion())
            ->setFamilyMember($member)
            ->addIngredient((new DishPortionIngredient())->setIngredient($ingredient)->setAmount($amount));
    }
}

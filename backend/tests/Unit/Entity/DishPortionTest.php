<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\DishPortion;
use App\Entity\DishPortionIngredient;
use App\Entity\Ingredient;
use App\Enum\Unit;
use PHPUnit\Framework\TestCase;

final class DishPortionTest extends TestCase
{
    public function testEmptyPortionGivesZeroTotals(): void
    {
        self::assertSame(
            ['kcal' => 0.0, 'protein' => 0.0, 'fat' => 0.0, 'carbs' => 0.0],
            (new DishPortion())->calculateNutrition()
        );
    }

    public function testSumsIngredientsProportionallyToAmount(): void
    {
        $portion = (new DishPortion())
            ->addIngredient($this->item($this->gramIngredient(112.0, 3.8, 0.2, 23.0), 150.0))
            ->addIngredient($this->item($this->gramIngredient(165.0, 31.0, 3.6, 0.0), 100.0));

        self::assertSame(
            // 112*1.5 + 165 = 333; 3.8*1.5 + 31 = 36.7; 0.2*1.5 + 3.6 = 3.9; 23*1.5 = 34.5
            ['kcal' => 333.0, 'protein' => 36.7, 'fat' => 3.9, 'carbs' => 34.5],
            $portion->calculateNutrition()
        );
    }

    public function testPieceIngredientConvertsThroughPieceWeight(): void
    {
        $egg = (new Ingredient())
            ->setUnit(Unit::Pieces)
            ->setPieceWeightGrams(55.0)
            ->setKcalPer100(155.0)
            ->setProteinPer100(13.0)
            ->setFatPer100(11.0)
            ->setCarbsPer100(1.1);

        $portion = (new DishPortion())->addIngredient($this->item($egg, 2.0));

        // 2 шт × 55 г = 110 г → 155 × 1.1 = 170.5 ккал
        self::assertSame(
            ['kcal' => 170.5, 'protein' => 14.3, 'fat' => 12.1, 'carbs' => 1.2],
            $portion->calculateNutrition()
        );
    }

    public function testRoundsTotalsToOneDecimal(): void
    {
        $portion = (new DishPortion())
            ->addIngredient($this->item($this->gramIngredient(33.3, 3.33, 0.33, 3.33), 33.0));

        $nutrition = $portion->calculateNutrition();

        self::assertSame(11.0, $nutrition['kcal']);
        self::assertSame(1.1, $nutrition['protein']);
        self::assertSame(0.1, $nutrition['fat']);
        self::assertSame(1.1, $nutrition['carbs']);
    }

    private function gramIngredient(float $kcal, float $protein, float $fat, float $carbs): Ingredient
    {
        return (new Ingredient())
            ->setUnit(Unit::Grams)
            ->setKcalPer100($kcal)
            ->setProteinPer100($protein)
            ->setFatPer100($fat)
            ->setCarbsPer100($carbs);
    }

    private function item(Ingredient $ingredient, float $amount): DishPortionIngredient
    {
        return (new DishPortionIngredient())->setIngredient($ingredient)->setAmount($amount);
    }
}

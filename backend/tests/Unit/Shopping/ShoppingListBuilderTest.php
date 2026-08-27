<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shopping;

use App\Entity\Dish;
use App\Entity\DishPortion;
use App\Entity\DishPortionIngredient;
use App\Entity\FamilyMember;
use App\Entity\Ingredient;
use App\Entity\MealPlanEntry;
use App\Enum\IngredientCategory;
use App\Enum\Unit;
use App\Shopping\ShoppingListBuilder;
use PHPUnit\Framework\TestCase;

final class ShoppingListBuilderTest extends TestCase
{
    public function testSumsPortionsPerMemberAndRawProductsGroupedByCategory(): void
    {
        $husband = $this->member(1);
        $wife = $this->member(2);
        $buckwheat = $this->ingredient(10, 'Гречка', IngredientCategory::GrainsBread, Unit::Grams);
        $chicken = $this->ingredient(11, 'Курка', IngredientCategory::MeatFish, Unit::Grams);
        $eggs = $this->ingredient(12, 'Яйця', IngredientCategory::Eggs, Unit::Pieces);

        // Страва з різною грамівкою на кожного
        $dish = (new Dish())->setName('Гречка з куркою')
            ->addPortion($this->portion($husband, [[$buckwheat, 150.0], [$chicken, 200.0]]))
            ->addPortion($this->portion($wife, [[$buckwheat, 100.0], [$chicken, 150.0]]));

        $entries = [
            (new MealPlanEntry())->setFamilyMember($husband)->setDish($dish),
            (new MealPlanEntry())->setFamilyMember($wife)->setDish($dish),
            (new MealPlanEntry())->setFamilyMember($husband)->setDish($dish), // ще раз наступного дня
            (new MealPlanEntry())->setFamilyMember($wife)->setIngredient($eggs)->setAmount(2.0),
            (new MealPlanEntry())->setFamilyMember($husband)->setIngredient($eggs)->setAmount(3.0),
        ];

        $groups = (new ShoppingListBuilder())->build($entries);

        // Порядок груп — як у enum: м'ясо, яйця, ..., крупи
        self::assertSame(['meat_fish', 'eggs', 'grains_bread'], array_column($groups, 'category'));

        [$meat, $eggGroup, $grains] = $groups;
        self::assertSame([['ingredientId' => 11, 'name' => 'Курка', 'unit' => 'g', 'amount' => 550.0, 'uses' => 3]], $meat['items']);
        self::assertSame([['ingredientId' => 12, 'name' => 'Яйця', 'unit' => 'pcs', 'amount' => 5.0, 'uses' => 2]], $eggGroup['items']);
        self::assertSame([['ingredientId' => 10, 'name' => 'Гречка', 'unit' => 'g', 'amount' => 400.0, 'uses' => 3]], $grains['items']);
    }

    public function testSkipsDishesWithoutPortionForMember(): void
    {
        $husband = $this->member(1);
        $guest = $this->member(3);
        $rice = $this->ingredient(20, 'Рис', IngredientCategory::GrainsBread, Unit::Grams);
        $dish = (new Dish())->setName('Рис')->addPortion($this->portion($husband, [[$rice, 100.0]]));

        $groups = (new ShoppingListBuilder())->build([
            (new MealPlanEntry())->setFamilyMember($guest)->setDish($dish),
        ]);

        self::assertSame([], $groups);
    }

    private function member(int $id): FamilyMember
    {
        $member = (new FamilyMember())->setName('m'.$id)->setKcalTarget(2000);
        (new \ReflectionProperty(FamilyMember::class, 'id'))->setValue($member, $id);

        return $member;
    }

    private function ingredient(int $id, string $name, IngredientCategory $category, Unit $unit): Ingredient
    {
        $ingredient = (new Ingredient())->setName($name)->setCategory($category)->setUnit($unit);
        (new \ReflectionProperty(Ingredient::class, 'id'))->setValue($ingredient, $id);

        return $ingredient;
    }

    /** @param list<array{0: Ingredient, 1: float}> $rows */
    private function portion(FamilyMember $member, array $rows): DishPortion
    {
        $portion = (new DishPortion())->setFamilyMember($member);
        foreach ($rows as [$ingredient, $amount]) {
            $portion->addIngredient((new DishPortionIngredient())->setIngredient($ingredient)->setAmount($amount));
        }

        return $portion;
    }
}

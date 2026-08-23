<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Dish;
use App\Entity\DishPortion;
use App\Entity\DishPortionIngredient;
use App\Entity\FamilyMember;
use App\Entity\Ingredient;
use App\Enum\IngredientCategory;
use App\Enum\MealType;
use App\Enum\Unit;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $man = (new FamilyMember())
            ->setName('Чоловік')
            ->setKcalTarget(2250)
            ->setProteinTarget(130)
            ->setFatTarget(75)
            ->setCarbsTarget(265);
        $woman = (new FamilyMember())
            ->setName('Жінка')
            ->setKcalTarget(2100)
            ->setProteinTarget(105)
            ->setFatTarget(75)
            ->setCarbsTarget(250);
        $manager->persist($man);
        $manager->persist($woman);

        /** @var array<string, Ingredient> $ingredients */
        $ingredients = [];
        foreach ($this->readJson('ingredients.json') as $row) {
            $ingredient = (new Ingredient())
                ->setName($row['name'])
                ->setCategory(IngredientCategory::from($row['category']))
                ->setUnit(Unit::from($row['unit']))
                ->setKcalPer100((float) $row['kcal'])
                ->setProteinPer100((float) $row['protein'])
                ->setFatPer100((float) $row['fat'])
                ->setCarbsPer100((float) $row['carbs'])
                ->setPieceWeightGrams(isset($row['pieceWeightGrams']) ? (float) $row['pieceWeightGrams'] : null);
            $manager->persist($ingredient);
            $ingredients[$row['name']] = $ingredient;
        }

        foreach ($this->readJson('dishes.json') as $row) {
            $dish = (new Dish())
                ->setCode($row['code'])
                ->setName($row['name'])
                ->setCategory(MealType::from($row['category']))
                ->setRecipe($row['recipe'] ?? null)
                ->setBatchCooking($row['batch'] ?? false);

            foreach (['man' => $man, 'woman' => $woman] as $key => $member) {
                if (!isset($row[$key])) {
                    continue;
                }
                $portion = (new DishPortion())->setFamilyMember($member);
                foreach (explode(';', $row[$key]) as $pair) {
                    [$name, $amount] = explode(':', $pair);
                    if (!isset($ingredients[$name])) {
                        throw new \RuntimeException(sprintf('Unknown ingredient "%s" in dish %s', $name, $row['code']));
                    }
                    $portion->addIngredient(
                        (new DishPortionIngredient())
                            ->setIngredient($ingredients[$name])
                            ->setAmount((float) $amount)
                    );
                }
                $dish->addPortion($portion);
            }

            $manager->persist($dish);
        }

        $manager->flush();
    }

    /** @return list<array<string, mixed>> */
    private function readJson(string $file): array
    {
        $path = __DIR__.'/data/'.$file;
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Cannot read '.$path);
        }

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}

<?php

declare(strict_types=1);

namespace App\Shopping;

use App\Entity\Ingredient;
use App\Entity\MealPlanEntry;
use App\Enum\IngredientCategory;

/**
 * Агрегує інгредієнти меню за період у список закупівель: страви розкладаються
 * на порції конкретної людини, окремі продукти беруться як є. Кількості
 * підсумовуються в одиниці інгредієнта (г/мл/шт) і групуються за категорією.
 */
final class ShoppingListBuilder
{
    /**
     * @param iterable<MealPlanEntry> $entries
     *
     * @return list<array{category: string, items: list<array{ingredientId: int, name: string, unit: string, amount: float, uses: int}>}>
     */
    public function build(iterable $entries): array
    {
        /** @var array<int, array{ingredient: Ingredient, amount: float, uses: int}> $totals */
        $totals = [];
        $add = static function (Ingredient $ingredient, float $amount) use (&$totals): void {
            $id = $ingredient->getId() ?? 0;
            $totals[$id] ??= ['ingredient' => $ingredient, 'amount' => 0.0, 'uses' => 0];
            $totals[$id]['amount'] += $amount;
            ++$totals[$id]['uses'];
        };

        foreach ($entries as $entry) {
            $dish = $entry->getDish();
            $member = $entry->getFamilyMember();
            if ($dish !== null && $member !== null) {
                $portion = $dish->getPortionFor($member);
                if ($portion === null) {
                    continue;
                }
                foreach ($portion->getIngredients() as $item) {
                    $ingredient = $item->getIngredient();
                    if ($ingredient !== null) {
                        $add($ingredient, $item->getAmount());
                    }
                }
                continue;
            }

            $ingredient = $entry->getIngredient();
            if ($ingredient !== null && $entry->getAmount() !== null) {
                $add($ingredient, $entry->getAmount());
            }
        }

        $groups = [];
        foreach (IngredientCategory::cases() as $category) {
            $items = [];
            foreach ($totals as $row) {
                if ($row['ingredient']->getCategory() !== $category) {
                    continue;
                }
                $items[] = [
                    'ingredientId' => $row['ingredient']->getId() ?? 0,
                    'name' => $row['ingredient']->getName(),
                    'unit' => $row['ingredient']->getUnit()->value,
                    'amount' => round($row['amount'], 1),
                    'uses' => $row['uses'],
                ];
            }
            if ($items === []) {
                continue;
            }
            usort($items, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
            $groups[] = ['category' => $category->value, 'items' => $items];
        }

        return $groups;
    }
}

<?php

declare(strict_types=1);

namespace App\Enum;

enum IngredientCategory: string
{
    case MeatFish = 'meat_fish';
    case Eggs = 'eggs';
    case Dairy = 'dairy';
    case GrainsBread = 'grains_bread';
    case VegetablesGreens = 'vegetables_greens';
    case FruitsBerries = 'fruits_berries';
    case NutsSeedsDried = 'nuts_seeds_dried';
    case Legumes = 'legumes';
    case OilsSauces = 'oils_sauces';
    case Other = 'other';
}

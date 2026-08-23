<?php

declare(strict_types=1);

namespace App\Enum;

enum Unit: string
{
    case Grams = 'g';
    case Milliliters = 'ml';
    case Pieces = 'pcs';
}

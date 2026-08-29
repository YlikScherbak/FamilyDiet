<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Спільні OpenAPI-схеми відповідей. Клас не має логіки — це лише носій атрибутів,
 * на які посилаються контролери через ref: '#/components/schemas/…'.
 */
#[OA\Schema(
    schema: 'Error',
    properties: [new OA\Property(property: 'error', type: 'string', example: 'Страву не знайдено')],
)]
#[OA\Schema(
    schema: 'FieldErrors',
    description: 'Помилки по полях: ключ — назва поля, значення — повідомлення мовою Accept-Language',
    properties: [new OA\Property(property: 'errors', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string'))],
)]
#[OA\Schema(
    schema: 'FamilyMember',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Чоловік'),
        new OA\Property(property: 'kcalTarget', type: 'integer', example: 2250),
        new OA\Property(property: 'proteinTarget', type: 'integer', nullable: true, example: 130),
        new OA\Property(property: 'fatTarget', type: 'integer', nullable: true, example: 75),
        new OA\Property(property: 'carbsTarget', type: 'integer', nullable: true, example: 265),
    ],
)]
#[OA\Schema(
    schema: 'Ingredient',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 42),
        new OA\Property(property: 'name', type: 'string', example: 'Гречка варена'),
        new OA\Property(property: 'nameEn', type: 'string', nullable: true, example: 'Buckwheat, cooked'),
        new OA\Property(property: 'category', type: 'string', enum: ['meat_fish', 'eggs', 'dairy', 'grains_bread', 'vegetables_greens', 'fruits_berries', 'nuts_seeds_dried', 'legumes', 'oils_sauces', 'other']),
        new OA\Property(property: 'unit', type: 'string', enum: ['g', 'ml', 'pcs']),
        new OA\Property(property: 'kcalPer100', type: 'number', example: 110),
        new OA\Property(property: 'proteinPer100', type: 'number', example: 4.2),
        new OA\Property(property: 'fatPer100', type: 'number', example: 1.1),
        new OA\Property(property: 'carbsPer100', type: 'number', example: 21.3),
        new OA\Property(property: 'pieceWeightGrams', type: 'number', nullable: true, description: 'Вага 1 шт у грамах — лише для unit=pcs'),
    ],
)]
#[OA\Schema(
    schema: 'IngredientInput',
    required: ['name', 'category', 'unit'],
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'category', type: 'string'),
        new OA\Property(property: 'unit', type: 'string', enum: ['g', 'ml', 'pcs']),
        new OA\Property(property: 'kcalPer100', type: 'number'),
        new OA\Property(property: 'proteinPer100', type: 'number'),
        new OA\Property(property: 'fatPer100', type: 'number'),
        new OA\Property(property: 'carbsPer100', type: 'number'),
        new OA\Property(property: 'pieceWeightGrams', type: 'number', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'Nutrition',
    properties: [
        new OA\Property(property: 'kcal', type: 'number', example: 597.0),
        new OA\Property(property: 'protein', type: 'number', example: 24.1),
        new OA\Property(property: 'fat', type: 'number', example: 18.3),
        new OA\Property(property: 'carbs', type: 'number', example: 72.5),
    ],
)]
#[OA\Schema(
    schema: 'DishPortion',
    description: 'Порція страви для конкретного члена сім\'ї; калорійність обчислюється з інгредієнтів',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'familyMemberId', type: 'integer'),
        new OA\Property(property: 'nutrition', ref: '#/components/schemas/Nutrition'),
        new OA\Property(property: 'ingredients', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'ingredientId', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'unit', type: 'string'),
            new OA\Property(property: 'amount', type: 'number', description: 'Кількість у одиниці інгредієнта'),
        ])),
    ],
)]
#[OA\Schema(
    schema: 'Dish',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'code', type: 'string', nullable: true, example: 'B01'),
        new OA\Property(property: 'name', type: 'string', example: 'Вівсянка з ягодами'),
        new OA\Property(property: 'category', type: 'string', enum: ['breakfast', 'lunch', 'dinner', 'snack', 'extra_snack']),
        new OA\Property(property: 'recipe', type: 'string', nullable: true),
        new OA\Property(property: 'youtubeUrl', type: 'string', nullable: true),
        new OA\Property(property: 'batchCooking', type: 'boolean'),
        new OA\Property(property: 'portions', type: 'array', items: new OA\Items(ref: '#/components/schemas/DishPortion')),
    ],
)]
#[OA\Schema(
    schema: 'DishInput',
    required: ['name', 'category'],
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'code', type: 'string', nullable: true),
        new OA\Property(property: 'category', type: 'string', enum: ['breakfast', 'lunch', 'dinner', 'snack', 'extra_snack']),
        new OA\Property(property: 'recipe', type: 'string', nullable: true),
        new OA\Property(property: 'youtubeUrl', type: 'string', nullable: true),
        new OA\Property(property: 'batchCooking', type: 'boolean'),
        new OA\Property(property: 'portions', type: 'array', description: 'Не більше однієї порції на члена сім\'ї', items: new OA\Items(properties: [
            new OA\Property(property: 'familyMemberId', type: 'integer'),
            new OA\Property(property: 'ingredients', type: 'array', items: new OA\Items(properties: [
                new OA\Property(property: 'ingredientId', type: 'integer'),
                new OA\Property(property: 'amount', type: 'number'),
            ])),
        ])),
    ],
)]
#[OA\Schema(
    schema: 'MealPlanEntry',
    description: 'Запис меню — або страва, або окремий продукт із кількістю (рівно одне з двох)',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'date', type: 'string', format: 'date'),
        new OA\Property(property: 'familyMemberId', type: 'integer'),
        new OA\Property(property: 'slot', type: 'string', enum: ['breakfast', 'lunch', 'dinner', 'snack', 'extra_snack']),
        new OA\Property(property: 'type', type: 'string', enum: ['dish', 'product']),
        new OA\Property(property: 'dish', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'code', type: 'string', nullable: true),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'category', type: 'string'),
            new OA\Property(property: 'batchCooking', type: 'boolean'),
        ], type: 'object'),
        new OA\Property(property: 'ingredient', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'unit', type: 'string'),
        ], type: 'object'),
        new OA\Property(property: 'amount', type: 'number', nullable: true),
        new OA\Property(property: 'nutrition', ref: '#/components/schemas/Nutrition', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'DaySummary',
    properties: [
        new OA\Property(property: 'date', type: 'string', format: 'date'),
        new OA\Property(property: 'familyMemberId', type: 'integer'),
        new OA\Property(property: 'kcal', type: 'number'),
        new OA\Property(property: 'protein', type: 'number'),
        new OA\Property(property: 'fat', type: 'number'),
        new OA\Property(property: 'carbs', type: 'number'),
    ],
)]
#[OA\Schema(
    schema: 'HealthEvent',
    description: 'Подія журналу здоров\'я. Структуровані дані типу — в payload: pressure → systolic/diastolic/pulse, weight → kg, headache/migraine/symptom → severity (1–5), custom → title',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'familyMemberId', type: 'integer'),
        new OA\Property(property: 'date', type: 'string', format: 'date'),
        new OA\Property(property: 'time', type: 'string', nullable: true, example: '07:30', description: 'ГГ:ХХ або null для події «протягом дня»'),
        new OA\Property(property: 'type', type: 'string', enum: ['pressure', 'weight', 'headache', 'migraine', 'medication', 'symptom', 'note', 'custom']),
        new OA\Property(property: 'payload', type: 'object', example: ['systolic' => 138, 'diastolic' => 88, 'pulse' => 74]),
        new OA\Property(property: 'note', type: 'string', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'HealthEventInput',
    required: ['familyMemberId', 'date', 'type'],
    properties: [
        new OA\Property(property: 'familyMemberId', type: 'integer'),
        new OA\Property(property: 'date', type: 'string', format: 'date'),
        new OA\Property(property: 'time', type: 'string', nullable: true, example: '07:30'),
        new OA\Property(property: 'type', type: 'string', enum: ['pressure', 'weight', 'headache', 'migraine', 'medication', 'symptom', 'note', 'custom']),
        new OA\Property(property: 'payload', type: 'object', example: ['systolic' => 138, 'diastolic' => 88, 'pulse' => 74]),
        new OA\Property(property: 'note', type: 'string', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'ShoppingList',
    properties: [
        new OA\Property(property: 'from', type: 'string', format: 'date'),
        new OA\Property(property: 'to', type: 'string', format: 'date'),
        new OA\Property(property: 'entries', type: 'integer', description: 'Скільки записів меню агреговано'),
        new OA\Property(property: 'groups', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'category', type: 'string'),
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
                new OA\Property(property: 'ingredientId', type: 'integer'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'unit', type: 'string', enum: ['g', 'ml', 'pcs']),
                new OA\Property(property: 'amount', type: 'number'),
                new OA\Property(property: 'uses', type: 'integer', description: 'У скількох записах меню зустрічається'),
            ])),
        ])),
    ],
)]
final class Schemas
{
}

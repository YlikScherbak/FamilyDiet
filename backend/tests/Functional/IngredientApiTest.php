<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class IngredientApiTest extends ApiTestCase
{
    public function testCrudLifecycle(): void
    {
        $this->request('POST', '/api/ingredients', [
            'name' => 'Тестова гречка', 'category' => 'grains_bread', 'unit' => 'g',
            'kcalPer100' => 110, 'proteinPer100' => 4.2, 'fatPer100' => 1.1, 'carbsPer100' => 21.3,
        ]);
        $this->assertStatus(201);
        /** @var array{id: int, name: string} $created */
        $created = $this->responseJson();
        self::assertSame('Тестова гречка', $created['name']);

        $this->request('PUT', '/api/ingredients/'.$created['id'], [
            'name' => 'Тестова гречка', 'category' => 'grains_bread', 'unit' => 'g',
            'kcalPer100' => 112, 'proteinPer100' => 4.2, 'fatPer100' => 1.1, 'carbsPer100' => 21.3,
        ]);
        $this->assertStatus(200);

        $this->request('GET', '/api/ingredients/'.$created['id']);
        $this->assertStatus(200);
        /** @var array{kcalPer100: float|int} $fetched */
        $fetched = $this->responseJson();
        self::assertSame(112.0, (float) $fetched['kcalPer100']);

        $this->request('DELETE', '/api/ingredients/'.$created['id']);
        $this->assertStatus(204);

        $this->request('GET', '/api/ingredients/'.$created['id']);
        $this->assertStatus(404);
    }

    public function testRejectsUnknownCategoryAndBlankName(): void
    {
        $this->request('POST', '/api/ingredients', [
            'name' => 'Щось', 'category' => 'sweets', 'unit' => 'g',
        ]);
        $this->assertStatus(422);

        $this->request('POST', '/api/ingredients', [
            'name' => '   ', 'category' => 'other', 'unit' => 'g',
        ]);
        $this->assertStatus(422);
    }

    public function testPieceUnitRequiresPieceWeight(): void
    {
        $payload = [
            'name' => 'Тестове яйце', 'category' => 'eggs', 'unit' => 'pcs',
            'kcalPer100' => 155, 'proteinPer100' => 13, 'fatPer100' => 11, 'carbsPer100' => 1.1,
        ];

        $this->request('POST', '/api/ingredients', $payload);
        $this->assertStatus(422);

        $this->request('POST', '/api/ingredients', $payload + ['pieceWeightGrams' => 55]);
        $this->assertStatus(201);
    }

    public function testDeleteUsedInDishReturnsConflict(): void
    {
        $member = $this->createMember('Інгр-1');
        $ingredient = $this->createIngredient('Курка для страви');
        $this->createDish('Курка запечена', $member, $ingredient);

        $this->request('DELETE', '/api/ingredients/'.$ingredient->getId());
        $this->assertStatus(409);
    }

    public function testSearchFindsByNameFragment(): void
    {
        $this->createIngredient('Абсолютно унікальний батат');
        $this->createIngredient('Звичайна морква');

        $this->request('GET', '/api/ingredients?search=унікальний батат');
        $this->assertStatus(200);
        /** @var array<int, array{name: string}> $items */
        $items = $this->responseJson();

        self::assertCount(1, $items);
        self::assertSame('Абсолютно унікальний батат', $items[0]['name']);
    }
}

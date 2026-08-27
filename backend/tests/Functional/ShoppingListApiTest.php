<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class ShoppingListApiTest extends ApiTestCase
{
    public function testAggregatesMenuOfPeriod(): void
    {
        $member = $this->createMember('Закупи-1');
        $oats = $this->createIngredient('Вівсянка для закупів');
        $dish = $this->createDish('Каша для закупів', $member, $oats, 80.0);

        foreach (['2026-03-02', '2026-03-03'] as $date) {
            $this->request('POST', '/api/meal-plan/entries', [
                'date' => $date, 'familyMemberId' => $member->getId(), 'slot' => 'breakfast', 'dishId' => $dish->getId(),
            ]);
        }
        // Окремий продукт у тому ж періоді і той самий інгредієнт
        $this->request('POST', '/api/meal-plan/entries', [
            'date' => '2026-03-04', 'familyMemberId' => $member->getId(), 'slot' => 'snack',
            'ingredientId' => $oats->getId(), 'amount' => 40,
        ]);
        // Поза періодом — не рахується
        $this->request('POST', '/api/meal-plan/entries', [
            'date' => '2026-03-20', 'familyMemberId' => $member->getId(), 'slot' => 'breakfast', 'dishId' => $dish->getId(),
        ]);

        $this->request('GET', '/api/shopping-list?from=2026-03-02&to=2026-03-08');
        $this->assertStatus(200);
        /** @var array{entries: int, groups: list<array{category: string, items: list<array{name: string, amount: float|int, uses: int}>}>} $data */
        $data = $this->responseJson();

        self::assertSame(3, $data['entries']);
        $items = array_merge(...array_column($data['groups'], 'items'));
        $oatsRow = array_values(array_filter($items, static fn (array $i): bool => $i['name'] === 'Вівсянка для закупів'))[0];
        self::assertSame(200.0, (float) $oatsRow['amount']); // 80 + 80 + 40
        self::assertSame(3, $oatsRow['uses']);
    }

    public function testRejectsBadOrTooLongRange(): void
    {
        $this->request('GET', '/api/shopping-list?from=2026-03-10&to=2026-03-01');
        $this->assertStatus(400);

        $this->request('GET', '/api/shopping-list?from=2026-01-01&to=2026-12-31');
        $this->assertStatus(422);
    }
}

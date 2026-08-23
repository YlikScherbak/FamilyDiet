<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class MealPlanDayTest extends ApiTestCase
{
    public function testReplaceDayReplacesPreviousEntries(): void
    {
        $member = $this->createMember('День-1');
        $dish = $this->createDish('Каша', $member, $this->createIngredient('Вівсянка суха'));
        $ingredient = $this->createIngredient('Кефір 2.5%');

        $this->request('PUT', '/api/meal-plan/day', [
            'date' => '2026-03-02', 'familyMemberId' => $member->getId(),
            'entries' => [
                ['slot' => 'breakfast', 'dishId' => $dish->getId()],
                ['slot' => 'snack', 'ingredientId' => $ingredient->getId(), 'amount' => 250],
            ],
        ]);
        $this->assertStatus(200);
        self::assertSame(['saved' => 2], $this->responseJson());

        // повторне збереження дня з одним записом — старі два зникають
        $this->request('PUT', '/api/meal-plan/day', [
            'date' => '2026-03-02', 'familyMemberId' => $member->getId(),
            'entries' => [['slot' => 'dinner', 'dishId' => $dish->getId()]],
        ]);
        $this->assertStatus(200);

        $entries = $this->entriesOn('2026-03-02');
        self::assertCount(1, $entries);
        self::assertSame('dinner', $entries[0]['slot']);
    }

    public function testReplaceDayRejectsUnknownSlotWithoutSavingAnything(): void
    {
        $member = $this->createMember('День-2');
        $dish = $this->createDish('Салат', $member, $this->createIngredient('Овочі для салату'));

        $this->request('PUT', '/api/meal-plan/day', [
            'date' => '2026-03-03', 'familyMemberId' => $member->getId(),
            'entries' => [
                ['slot' => 'breakfast', 'dishId' => $dish->getId()],
                ['slot' => 'brunch', 'dishId' => $dish->getId()],
            ],
        ]);
        $this->assertStatus(422);
        self::assertCount(0, $this->entriesOn('2026-03-03'));
    }

    public function testAddEntryRequiresExactlyDishOrIngredient(): void
    {
        $member = $this->createMember('День-3');
        $dish = $this->createDish('Рагу', $member, $this->createIngredient('Овочі для рагу'));

        $this->request('POST', '/api/meal-plan/entries', [
            'date' => '2026-03-04', 'familyMemberId' => $member->getId(), 'slot' => 'lunch',
        ]);
        $this->assertStatus(422);

        $this->request('POST', '/api/meal-plan/entries', [
            'date' => '2026-03-04', 'familyMemberId' => $member->getId(), 'slot' => 'lunch',
            'dishId' => $dish->getId(), 'ingredientId' => 1,
        ]);
        $this->assertStatus(422);
    }

    public function testAddProductEntryRequiresPositiveAmount(): void
    {
        $member = $this->createMember('День-4');
        $ingredient = $this->createIngredient('Йогурт натуральний');

        $this->request('POST', '/api/meal-plan/entries', [
            'date' => '2026-03-05', 'familyMemberId' => $member->getId(), 'slot' => 'snack',
            'ingredientId' => $ingredient->getId(), 'amount' => 0,
        ]);
        $this->assertStatus(422);

        $this->request('POST', '/api/meal-plan/entries', [
            'date' => '2026-03-05', 'familyMemberId' => $member->getId(), 'slot' => 'snack',
            'ingredientId' => $ingredient->getId(), 'amount' => 150,
        ]);
        $this->assertStatus(201);
    }
}

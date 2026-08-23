<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class MealPlanCopyTest extends ApiTestCase
{
    public function testRepeatedCopyDoesNotDuplicateEntries(): void
    {
        $member = $this->createMember('Копi-1');
        $dish = $this->createDish('Гречка з куркою', $member, $this->createIngredient('Гречка варена'));

        $this->request('POST', '/api/meal-plan/entries', [
            'date' => '2026-03-02', 'familyMemberId' => $member->getId(),
            'slot' => 'breakfast', 'dishId' => $dish->getId(),
        ]);
        $this->assertStatus(201);

        $copy = ['sourceFrom' => '2026-03-02', 'sourceTo' => '2026-03-02', 'targetFrom' => '2026-03-03'];
        $this->request('POST', '/api/meal-plan/copy', $copy);
        $this->assertStatus(201);
        $this->request('POST', '/api/meal-plan/copy', $copy);
        $this->assertStatus(201);

        self::assertCount(1, $this->entriesOn('2026-03-03'));
    }

    public function testMemberScopedCopyLeavesOtherMembersTargetIntact(): void
    {
        $one = $this->createMember('Копi-2а');
        $two = $this->createMember('Копi-2б');
        $dish = $this->createDish('Суп', $one, $this->createIngredient('Овочі для супу'));

        // джерело: записи обох; ціль: наявний запис другого
        $this->request('POST', '/api/meal-plan/entries', [
            'date' => '2026-03-09', 'familyMemberId' => $one->getId(), 'slot' => 'lunch', 'dishId' => $dish->getId(),
        ]);
        $this->request('POST', '/api/meal-plan/entries', [
            'date' => '2026-03-09', 'familyMemberId' => $two->getId(), 'slot' => 'lunch', 'dishId' => $dish->getId(),
        ]);
        $this->request('POST', '/api/meal-plan/entries', [
            'date' => '2026-03-10', 'familyMemberId' => $two->getId(), 'slot' => 'dinner', 'dishId' => $dish->getId(),
        ]);

        $this->request('POST', '/api/meal-plan/copy', [
            'sourceFrom' => '2026-03-09', 'sourceTo' => '2026-03-09', 'targetFrom' => '2026-03-10',
            'familyMemberId' => $one->getId(),
        ]);
        $this->assertStatus(201);
        self::assertSame(['copied' => 1], $this->responseJson());

        $byMember = [];
        foreach ($this->entriesOn('2026-03-10') as $entry) {
            $byMember[$entry['familyMemberId']][] = $entry['slot'];
        }

        self::assertSame(['lunch'], $byMember[$one->getId()] ?? []);
        self::assertSame(['dinner'], $byMember[$two->getId()] ?? [], 'чужий запис у цілі має вціліти');
    }

    public function testCopyWithEmptySourceClearsTargetRange(): void
    {
        $member = $this->createMember('Копi-3');
        $dish = $this->createDish('Омлет', $member, $this->createIngredient('Яйця для омлету'));

        $this->request('POST', '/api/meal-plan/entries', [
            'date' => '2026-03-17', 'familyMemberId' => $member->getId(), 'slot' => 'snack', 'dishId' => $dish->getId(),
        ]);

        // джерело порожнє → семантика заміни: ціль очищається
        $this->request('POST', '/api/meal-plan/copy', [
            'sourceFrom' => '2026-02-02', 'sourceTo' => '2026-02-02', 'targetFrom' => '2026-03-17',
        ]);
        $this->assertStatus(201);
        self::assertSame(['copied' => 0], $this->responseJson());
        self::assertCount(0, $this->entriesOn('2026-03-17'));
    }

    public function testRangeIsLimitedTo31Days(): void
    {
        $this->request('POST', '/api/meal-plan/copy', [
            'sourceFrom' => '2026-03-01', 'sourceTo' => '2026-04-01', 'targetFrom' => '2026-05-01',
        ]);
        $this->assertStatus(422);
    }

    public function testExactly31DaysIsAllowed(): void
    {
        $this->request('POST', '/api/meal-plan/copy', [
            'sourceFrom' => '2026-03-01', 'sourceTo' => '2026-03-31', 'targetFrom' => '2026-05-01',
        ]);
        $this->assertStatus(201);
    }

    public function testRejectsInvertedRangeAndUnknownMember(): void
    {
        $this->request('POST', '/api/meal-plan/copy', [
            'sourceFrom' => '2026-03-05', 'sourceTo' => '2026-03-01', 'targetFrom' => '2026-05-01',
        ]);
        $this->assertStatus(422);

        $this->request('POST', '/api/meal-plan/copy', [
            'sourceFrom' => '2026-03-01', 'sourceTo' => '2026-03-02', 'targetFrom' => '2026-05-01',
            'familyMemberId' => 999999,
        ]);
        $this->assertStatus(422);
    }
}

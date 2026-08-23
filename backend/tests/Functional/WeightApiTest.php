<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class WeightApiTest extends ApiTestCase
{
    public function testPostUpsertsByDate(): void
    {
        $member = $this->createMember('Вага-1');

        $this->request('POST', '/api/weight-entries', [
            'familyMemberId' => $member->getId(), 'date' => '2026-03-02', 'weightKg' => 81.4,
        ]);
        $this->assertStatus(201);

        $this->request('POST', '/api/weight-entries', [
            'familyMemberId' => $member->getId(), 'date' => '2026-03-02', 'weightKg' => 81.0,
        ]);
        $this->assertStatus(200);

        $this->request('GET', '/api/weight-entries?memberId='.$member->getId());
        /** @var array<int, array{date: string, weightKg: float}> $list */
        $list = $this->responseJson();

        self::assertCount(1, $list);
        // json_decode повертає 81 (int) для 81.0 — порівнюємо як float
        self::assertSame(81.0, (float) $list[0]['weightKg']);
    }

    public function testRejectsImplausibleWeight(): void
    {
        $member = $this->createMember('Вага-2');

        $this->request('POST', '/api/weight-entries', [
            'familyMemberId' => $member->getId(), 'date' => '2026-03-02', 'weightKg' => 500,
        ]);
        $this->assertStatus(422);
    }

    public function testWeeklyAveragesGroupByIsoWeek(): void
    {
        $member = $this->createMember('Вага-3');

        // ISO-тиждень 2026-03-02 (пн) … 2026-03-08 (нд), наступний — з 2026-03-09
        foreach ([['2026-03-02', 80.0], ['2026-03-04', 82.0], ['2026-03-09', 79.5]] as [$date, $kg]) {
            $this->request('POST', '/api/weight-entries', [
                'familyMemberId' => $member->getId(), 'date' => $date, 'weightKg' => $kg,
            ]);
        }

        $this->request('GET', '/api/weight-entries/weekly?memberId='.$member->getId());
        $this->assertStatus(200);
        /** @var array<int, array{weekStart: string, avgWeight: float, measurements: int}> $weeks */
        $weeks = $this->responseJson();

        self::assertCount(2, $weeks);
        self::assertSame('2026-03-02', $weeks[0]['weekStart']);
        self::assertSame(81.0, (float) $weeks[0]['avgWeight']);
        self::assertSame(2, $weeks[0]['measurements']);
        self::assertSame('2026-03-09', $weeks[1]['weekStart']);
        self::assertSame(79.5, (float) $weeks[1]['avgWeight']);
    }
}

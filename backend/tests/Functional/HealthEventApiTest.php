<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class HealthEventApiTest extends ApiTestCase
{
    public function testPressureEventLifecycle(): void
    {
        $member = $this->createMember('Тиск-1');

        $this->request('POST', '/api/health-events', [
            'familyMemberId' => $member->getId(),
            'date' => '2026-03-02',
            'time' => '07:30',
            'type' => 'pressure',
            'payload' => ['systolic' => 142, 'diastolic' => 91, 'pulse' => 78],
            'note' => 'Після кави',
        ]);
        $this->assertStatus(201);
        /** @var array{id: int, payload: array{systolic: int}} $created */
        $created = $this->responseJson();
        self::assertSame(142, $created['payload']['systolic']);

        $this->request('PUT', '/api/health-events/'.$created['id'], [
            'familyMemberId' => $member->getId(),
            'date' => '2026-03-02',
            'time' => '07:45',
            'type' => 'pressure',
            'payload' => ['systolic' => 138, 'diastolic' => 88, 'pulse' => 74],
        ]);
        $this->assertStatus(200);
        /** @var array{time: string, payload: array{systolic: int}, note: ?string} $updated */
        $updated = $this->responseJson();
        self::assertSame('07:45', $updated['time']);
        self::assertSame(138, $updated['payload']['systolic']);
        self::assertNull($updated['note']);

        $this->request('DELETE', '/api/health-events/'.$created['id']);
        $this->assertStatus(204);

        $this->request('GET', '/api/health-events?memberId='.$member->getId());
        self::assertCount(0, $this->responseJson());
    }

    public function testPressureValidation(): void
    {
        $member = $this->createMember('Тиск-2');
        $base = ['familyMemberId' => $member->getId(), 'date' => '2026-03-02', 'type' => 'pressure'];

        // систолічний нижчий за діастолічний
        $this->request('POST', '/api/health-events', $base + ['payload' => ['systolic' => 85, 'diastolic' => 90, 'pulse' => 70]]);
        $this->assertStatus(422);

        // за межами діапазону
        $this->request('POST', '/api/health-events', $base + ['payload' => ['systolic' => 300, 'diastolic' => 90, 'pulse' => 70]]);
        $this->assertStatus(422);

        // порожній payload
        $this->request('POST', '/api/health-events', $base + ['payload' => []]);
        $this->assertStatus(422);
    }

    public function testSimpleEventWithSeverityAndCustomTitle(): void
    {
        $member = $this->createMember('Подія-1');

        $this->request('POST', '/api/health-events', [
            'familyMemberId' => $member->getId(),
            'date' => '2026-03-03',
            'type' => 'migraine',
            'payload' => ['severity' => 4],
            'note' => 'Зранку, після недосипу',
        ]);
        $this->assertStatus(201);

        $this->request('POST', '/api/health-events', [
            'familyMemberId' => $member->getId(),
            'date' => '2026-03-03',
            'type' => 'migraine',
            'payload' => ['severity' => 9],
        ]);
        $this->assertStatus(422);

        // власна подія вимагає назви
        $this->request('POST', '/api/health-events', [
            'familyMemberId' => $member->getId(), 'date' => '2026-03-03', 'type' => 'custom', 'payload' => [],
        ]);
        $this->assertStatus(422);

        $this->request('POST', '/api/health-events', [
            'familyMemberId' => $member->getId(), 'date' => '2026-03-03', 'type' => 'custom',
            'payload' => ['title' => 'Запаморочення'],
        ]);
        $this->assertStatus(201);
    }

    public function testWeightEventValidationAndRounding(): void
    {
        $member = $this->createMember('Вага-1');
        $base = ['familyMemberId' => $member->getId(), 'date' => '2026-03-02', 'type' => 'weight'];

        $this->request('POST', '/api/health-events', $base + ['payload' => ['kg' => 82.46]]);
        $this->assertStatus(201);
        /** @var array{payload: array{kg: float}} $created */
        $created = $this->responseJson();
        self::assertSame(82.5, (float) $created['payload']['kg']);

        $this->request('POST', '/api/health-events', $base + ['payload' => ['kg' => 500]]);
        $this->assertStatus(422);

        $this->request('POST', '/api/health-events', $base + ['payload' => []]);
        $this->assertStatus(422);
    }

    public function testRejectsUnknownTypeAndBadTime(): void
    {
        $member = $this->createMember('Подія-2');

        $this->request('POST', '/api/health-events', [
            'familyMemberId' => $member->getId(), 'date' => '2026-03-03', 'type' => 'sneeze',
        ]);
        $this->assertStatus(422);

        $this->request('POST', '/api/health-events', [
            'familyMemberId' => $member->getId(), 'date' => '2026-03-03', 'type' => 'note', 'time' => '25:70',
        ]);
        $this->assertStatus(422);
    }

    public function testListFiltersByMemberTypeAndRange(): void
    {
        $one = $this->createMember('Фільтр-1');
        $two = $this->createMember('Фільтр-2');

        $post = fn (array $extra) => $this->request('POST', '/api/health-events', $extra + ['payload' => []]);
        $post(['familyMemberId' => $one->getId(), 'date' => '2026-03-01', 'type' => 'headache']);
        $post(['familyMemberId' => $one->getId(), 'date' => '2026-03-05', 'type' => 'note']);
        $post(['familyMemberId' => $one->getId(), 'date' => '2026-03-10', 'type' => 'headache']);
        $post(['familyMemberId' => $two->getId(), 'date' => '2026-03-05', 'type' => 'headache']);

        $this->request('GET', sprintf(
            '/api/health-events?memberId=%d&type=headache&from=2026-03-01&to=2026-03-07',
            $one->getId()
        ));
        $this->assertStatus(200);
        /** @var array<int, array{date: string, type: string}> $list */
        $list = $this->responseJson();

        self::assertCount(1, $list);
        self::assertSame('2026-03-01', $list[0]['date']);
    }

    public function testEventsAreSortedByDateAndTime(): void
    {
        $member = $this->createMember('Сорт-1');

        $post = fn (array $extra) => $this->request('POST', '/api/health-events', $extra + [
            'familyMemberId' => $member->getId(), 'type' => 'note', 'payload' => [],
        ]);
        $post(['date' => '2026-03-02', 'time' => '21:00']);
        $post(['date' => '2026-03-02', 'time' => '07:00']);
        $post(['date' => '2026-03-01']);

        $this->request('GET', '/api/health-events?memberId='.$member->getId());
        /** @var array<int, array{date: string, time: ?string}> $list */
        $list = $this->responseJson();

        self::assertSame(
            [['2026-03-01', null], ['2026-03-02', '07:00'], ['2026-03-02', '21:00']],
            array_map(static fn (array $e): array => [$e['date'], $e['time']], $list)
        );
    }
}

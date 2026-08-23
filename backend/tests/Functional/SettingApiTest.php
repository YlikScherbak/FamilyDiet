<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class SettingApiTest extends ApiTestCase
{
    public function testUnknownKeyGivesEmptyObject(): void
    {
        $this->request('GET', '/api/settings/health_chart');
        $this->assertStatus(200);
        self::assertSame([], $this->responseJson());
    }

    public function testPutThenGetRoundtrip(): void
    {
        $value = ['types' => ['migraine' => ['color' => '#b91c1c', 'style' => 'point']], 'enabled' => ['migraine']];

        $this->request('PUT', '/api/settings/health_chart', $value);
        $this->assertStatus(200);

        $this->request('GET', '/api/settings/health_chart');
        self::assertSame($value, $this->responseJson());

        // повторний PUT перезаписує
        $this->request('PUT', '/api/settings/health_chart', ['enabled' => []]);
        $this->request('GET', '/api/settings/health_chart');
        self::assertSame(['enabled' => []], $this->responseJson());
    }

    public function testRejectsOversizedValue(): void
    {
        $this->request('PUT', '/api/settings/health_chart', ['blob' => str_repeat('x', 20000)]);
        $this->assertStatus(422);
    }

    public function testRejectsBadKey(): void
    {
        $this->request('GET', '/api/settings/UPPER_CASE!');
        $this->assertStatus(404);
    }
}

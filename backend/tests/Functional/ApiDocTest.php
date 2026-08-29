<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class ApiDocTest extends ApiTestCase
{
    public function testOpenApiJsonDescribesEveryApiRoute(): void
    {
        $this->client->request('GET', '/api/doc.json');
        $this->assertStatus(200);

        $doc = $this->responseJson();
        self::assertSame('3.0.0', $doc['openapi'] ?? null);
        self::assertSame('FamilyDiet API', $doc['info']['title'] ?? null);

        $paths = array_keys($doc['paths'] ?? []);
        foreach (['/api/ingredients', '/api/dishes/{id}', '/api/meal-plan/day', '/api/meal-plan/copy', '/api/health-events', '/api/shopping-list', '/api/settings/{key}', '/api/health'] as $path) {
            self::assertContains($path, $paths, "Шлях $path відсутній у документації");
        }
        self::assertNotContains('/api/doc', $paths);
        self::assertNotContains('/api/doc.json', $paths);

        // Спільні схеми з App\OpenApi\Schemas потрапляють у components через SharedSchemasDescriber
        $schemas = array_keys($doc['components']['schemas'] ?? []);
        foreach (['Error', 'FieldErrors', 'Ingredient', 'Dish', 'MealPlanEntry', 'HealthEvent', 'ShoppingList'] as $schema) {
            self::assertContains($schema, $schemas);
        }

        // Кожна операція має summary і хоча б одну задокументовану відповідь
        foreach ($doc['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                self::assertNotEmpty($operation['summary'] ?? '', "$method $path без summary");
                self::assertNotEmpty($operation['responses'] ?? [], "$method $path без responses");
            }
        }
    }

    public function testSwaggerUiIsServed(): void
    {
        $this->client->request('GET', '/api/doc');
        $this->assertStatus(200);
        self::assertStringContainsString('swagger-ui', (string) $this->client->getResponse()->getContent());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Dish;
use App\Entity\FamilyMember;
use App\Entity\MealPlanEntry;
use App\Enum\MealType;

final class DayTemplateApiTest extends ApiTestCase
{
    /** @return array{0: FamilyMember, 1: Dish, 2: \App\Entity\Ingredient} */
    private function seedDay(string $date): array
    {
        $member = $this->createMember('Тестовий');
        $ingredient = $this->createIngredient('Шаблонний продукт');
        $dish = $this->createDish('Шаблонна страва', $member, $ingredient, 150.0);

        $this->em->persist(
            (new MealPlanEntry())
                ->setDate(new \DateTimeImmutable($date))
                ->setFamilyMember($member)
                ->setSlot(MealType::Breakfast)
                ->setDish($dish)
        );
        $this->em->persist(
            (new MealPlanEntry())
                ->setDate(new \DateTimeImmutable($date))
                ->setFamilyMember($member)
                ->setSlot(MealType::Snack)
                ->setIngredient($ingredient)
                ->setAmount(120.0)
        );
        $this->em->flush();

        return [$member, $dish, $ingredient];
    }

    public function testSnapshotApplyAndDelete(): void
    {
        [$member, $dish] = $this->seedDay('2031-03-03');

        // Знімок дня
        $this->request('POST', '/api/day-templates', ['name' => 'Мій день', 'date' => '2031-03-03']);
        $this->assertStatus(201);
        $template = $this->responseJson();
        self::assertSame('Мій день', $template['name']);
        self::assertSame(2, $template['items']);

        // Дублікат назви — 409
        $this->request('POST', '/api/day-templates', ['name' => 'Мій день', 'date' => '2031-03-03']);
        $this->assertStatus(409);

        // Застосування на іншу дату, на якій уже щось є — записи ЗАМІНЮЮТЬСЯ.
        // Після HTTP-запитів kernel скидає EntityManager — перечитуємо сутності за id.
        $member = $this->em->find(FamilyMember::class, $member->getId());
        $dish = $this->em->find(Dish::class, $dish->getId());
        self::assertNotNull($member);
        self::assertNotNull($dish);
        $this->em->persist(
            (new MealPlanEntry())
                ->setDate(new \DateTimeImmutable('2031-03-10'))
                ->setFamilyMember($member)
                ->setSlot(MealType::Dinner)
                ->setDish($dish)
        );
        $this->em->flush();

        $this->request('POST', "/api/day-templates/{$template['id']}/apply", ['date' => '2031-03-10']);
        $this->assertStatus(200);
        self::assertSame(2, $this->responseJson()['applied']);

        // Повторне застосування ідемпотентне
        $this->request('POST', "/api/day-templates/{$template['id']}/apply", ['date' => '2031-03-10']);
        $this->assertStatus(200);

        $rows = $this->em->getRepository(MealPlanEntry::class)->findBy(['date' => new \DateTimeImmutable('2031-03-10')]);
        self::assertCount(2, $rows);
        $slots = array_map(static fn (MealPlanEntry $e) => $e->getSlot()->value, $rows);
        sort($slots);
        self::assertSame(['breakfast', 'snack'], $slots);

        // Список
        $this->request('GET', '/api/day-templates');
        $this->assertStatus(200);
        self::assertCount(1, $this->responseJson());

        // Видалення шаблону не чіпає меню
        $this->request('DELETE', "/api/day-templates/{$template['id']}");
        $this->assertStatus(204);
        self::assertCount(2, $this->em->getRepository(MealPlanEntry::class)->findBy(['date' => new \DateTimeImmutable('2031-03-10')]));
    }

    public function testEmptyDayAndBadInput(): void
    {
        $this->request('POST', '/api/day-templates', ['name' => 'Порожній', 'date' => '2031-04-04']);
        $this->assertStatus(422);

        $this->request('POST', '/api/day-templates', ['name' => '', 'date' => 'not-a-date']);
        $this->assertStatus(422);
    }

    public function testDishDeletionCascadesOutOfTemplate(): void
    {
        [, $dish] = $this->seedDay('2031-05-05');

        $this->request('POST', '/api/day-templates', ['name' => 'З каскадом', 'date' => '2031-05-05']);
        $this->assertStatus(201);
        $id = $this->responseJson()['id'];

        // Страва в шаблоні, але НЕ в меню → видалення дозволене, запис зникає з шаблону каскадом
        $this->em->createQuery('DELETE FROM App\Entity\MealPlanEntry e')->execute();
        $this->request('DELETE', "/api/dishes/{$dish->getId()}");
        $this->assertStatus(204);

        $this->em->clear();
        $this->request('GET', '/api/day-templates');
        $list = $this->responseJson();
        self::assertSame(1, $list[0]['items'], 'у шаблоні мав лишитися тільки запис-продукт');

        $this->request('DELETE', "/api/day-templates/{$id}");
        $this->assertStatus(204);
    }
}

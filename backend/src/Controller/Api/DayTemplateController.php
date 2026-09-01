<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\DayTemplate;
use App\Entity\DayTemplateItem;
use App\Entity\MealPlanEntry;
use App\Repository\DayTemplateRepository;
use App\Repository\MealPlanEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/day-templates')]
#[OA\Tag(name: 'Day templates', description: "Іменовані шаблони цілого дня меню на всю сім'ю: знімок дати і транзакційне застосування на іншу дату")]
class DayTemplateController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DayTemplateRepository $templates,
        private readonly MealPlanEntryRepository $entries,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', methods: ['GET']),
        OA\Get(
            summary  : 'Список шаблонів',
            responses: [
                new OA\Response(
                    response   : 200,
                    description: 'За назвою',
                    content    : new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/DayTemplate'))
                ),
            ],
        )]
    public function list(): JsonResponse
    {
        $items = $this->templates->createQueryBuilder('t')
            ->leftJoin('t.items', 'i')->addSelect('i')
            ->orderBy('t.name', 'ASC')
            ->getQuery()->getResult();

        return $this->json(array_map($this->format(...), $items));
    }

    #[Route('', methods: ['POST']),
        OA\Post(
            summary    : 'Зберегти день як шаблон (знімок усіх записів дати)',
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
                required  : ['name', 'date'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 100),
                    new OA\Property(property: 'date', type: 'string', format: 'date', description: 'День, який знімаємо'),
                ]
            )),
            responses  : [
                new OA\Response(response: 201, description: 'Створено', content: new OA\JsonContent(ref: '#/components/schemas/DayTemplate')),
                new OA\Response(response: 409, description: 'Назва вже зайнята', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
                new OA\Response(response: 422, description: 'Порожній день або невалідні дані', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            ],
        )]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => $this->translator->trans('error.invalid_json')], 400);
        }

        $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 100);
        $date = $this->parseDate((string) ($data['date'] ?? ''));
        if ($name === '' || $date === null) {
            return $this->json(['error' => $this->translator->trans('error.day_template.name_and_date_required')], 422);
        }

        if ($this->templates->findOneBy(['name' => $name]) !== null) {
            return $this->json(['error' => $this->translator->trans('error.day_template.name_taken', ['%name%' => $name])], 409);
        }

        /** @var MealPlanEntry[] $dayEntries */
        $dayEntries = $this->entries->findBy(['date' => $date]);
        if ($dayEntries === []) {
            return $this->json(['error' => $this->translator->trans('error.day_template.day_empty')], 422);
        }

        $template = (new DayTemplate())->setName($name);
        foreach ($dayEntries as $entry) {
            $template->addItem(
                (new DayTemplateItem())
                    ->setFamilyMember($entry->getFamilyMember())
                    ->setSlot($entry->getSlot())
                    ->setDish($entry->getDish())
                    ->setIngredient($entry->getIngredient())
                    ->setAmount($entry->getAmount())
            );
        }

        $this->em->persist($template);
        $this->em->flush();

        return $this->json($this->format($template), 201);
    }

    #[Route('/{id<\d+>}/apply', methods: ['POST']),
        OA\Post(
            summary    : 'Застосувати шаблон на дату',
            description: 'В одній транзакції: видалити всі записи дати (для всіх членів сім\'ї) → вставити записи шаблону. Повторне застосування ідемпотентне.',
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
                required  : ['date'],
                properties: [new OA\Property(property: 'date', type: 'string', format: 'date')]
            )),
            responses  : [
                new OA\Response(response: 200, description: 'Застосовано', content: new OA\JsonContent(properties: [new OA\Property(property: 'applied', type: 'integer')])),
                new OA\Response(response: 404, description: 'Шаблон не знайдено'),
                new OA\Response(response: 422, description: 'Невалідна дата', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            ],
        )]
    public function apply(DayTemplate $template, Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => $this->translator->trans('error.invalid_json')], 400);
        }

        $date = $this->parseDate((string) ($data['date'] ?? ''));
        if ($date === null) {
            return $this->json(['error' => $this->translator->trans('error.day_template.date_required')], 422);
        }

        $applied = 0;
        $this->em->wrapInTransaction(function () use ($template, $date, &$applied): void {
            $this->em->createQuery('DELETE FROM App\Entity\MealPlanEntry e WHERE e.date = :date')
                ->execute(['date' => $date]);

            foreach ($template->getItems() as $item) {
                $this->em->persist(
                    (new MealPlanEntry())
                        ->setDate($date)
                        ->setFamilyMember($item->getFamilyMember())
                        ->setSlot($item->getSlot())
                        ->setDish($item->getDish())
                        ->setIngredient($item->getIngredient())
                        ->setAmount($item->getAmount())
                );
                ++$applied;
            }
        });

        return $this->json(['applied' => $applied]);
    }

    #[Route('/{id<\d+>}', methods: ['DELETE']),
        OA\Delete(
            summary  : 'Видалити шаблон',
            responses: [
                new OA\Response(response: 204, description: 'Видалено'),
                new OA\Response(response: 404, description: 'Не знайдено'),
            ],
        )]
    public function delete(DayTemplate $template): JsonResponse
    {
        $this->em->remove($template);
        $this->em->flush();

        return $this->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function format(DayTemplate $t): array
    {
        return [
            'id' => $t->getId(),
            'name' => $t->getName(),
            'items' => count($t->getItems()),
            'createdAt' => $t->getCreatedAt()->format('Y-m-d'),
        ];
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false ? null : $date;
    }
}

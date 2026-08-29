<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\MealPlanEntry;
use App\Enum\MealType;
use App\Repository\DishRepository;
use App\Repository\FamilyMemberRepository;
use App\Repository\IngredientRepository;
use App\Repository\MealPlanEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/meal-plan')]
#[OA\Tag(name: 'Meal plan', description: 'Календар меню: записи по слотах на кожного, денні підсумки КБЖВ, атомарна заміна дня, копіювання діапазону')]
class MealPlanController extends AbstractController
{
    private const COPY_MAX_DAYS = 31;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MealPlanEntryRepository $entries,
        private readonly FamilyMemberRepository $members,
        private readonly DishRepository $dishes,
        private readonly IngredientRepository $ingredients,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', methods: ['GET']),
        OA\Get(
            summary   : 'Записи меню за період + денні підсумки',
            parameters: [
                new OA\Parameter(
                    name    : 'from',
                    in      : 'query',
                    required: true,
                    schema  : new OA\Schema(type: 'string', format: 'date')
                ),
                new OA\Parameter(
                    name    : 'to',
                    in      : 'query',
                    required: true,
                    schema  : new OA\Schema(type: 'string', format: 'date')
                ),
            ],
            responses : [
                new OA\Response(
                    response   : 200,
                    description: 'OK',
                    content    : new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: 'entries',
                                type    : 'array',
                                items   : new OA\Items(ref: '#/components/schemas/MealPlanEntry')
                            ),
                            new OA\Property(
                                property: 'summaries',
                                type    : 'array',
                                items   : new OA\Items(ref: '#/components/schemas/DaySummary')
                            ),
                        ]
                    )
                ),
                new OA\Response(
                    response   : 400,
                    description: 'Немає from/to',
                    content    : new OA\JsonContent(ref: '#/components/schemas/Error')
                ),
            ]
        )]
    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->parseRange($request);
        if ($from === null || $to === null) {
            return $this->json(['error' => $this->translator->trans('error.meal_plan.from_to_required')], 400);
        }

        $result = $this->entries->createQueryBuilder('e')
            ->leftJoin('e.dish', 'd')->addSelect('d')
            ->leftJoin('d.portions', 'p')->addSelect('p')
            ->leftJoin('p.ingredients', 'pi')->addSelect('pi')
            ->leftJoin('pi.ingredient', 'i')->addSelect('i')
            ->leftJoin('e.ingredient', 'ei')->addSelect('ei')
            ->where('e.date >= :from AND e.date <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('e.date', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()->getResult();

        $entries = [];
        $summaries = [];
        foreach ($result as $entry) {
            /** @var MealPlanEntry $entry */
            $member = $entry->getFamilyMember();
            $dish = $entry->getDish();
            $ingredient = $entry->getIngredient();
            $nutrition = $entry->calculateNutrition();

            $entries[] = [
                'id' => $entry->getId(),
                'date' => $entry->getDate()?->format('Y-m-d'),
                'familyMemberId' => $member?->getId(),
                'slot' => $entry->getSlot()->value,
                'type' => $dish !== null ? 'dish' : 'product',
                'dish' => $dish === null ? null : [
                    'id' => $dish->getId(),
                    'code' => $dish->getCode(),
                    'name' => $dish->getName(),
                    'category' => $dish->getCategory()->value,
                    'batchCooking' => $dish->isBatchCooking(),
                ],
                'ingredient' => $ingredient === null ? null : [
                    'id' => $ingredient->getId(),
                    'name' => $ingredient->getName(),
                    'unit' => $ingredient->getUnit()->value,
                ],
                'amount' => $entry->getAmount(),
                'nutrition' => $nutrition,
            ];

            if ($nutrition !== null) {
                $key = $entry->getDate()?->format('Y-m-d').'|'.$member?->getId();
                $summaries[$key] ??= [
                    'date' => $entry->getDate()?->format('Y-m-d'),
                    'familyMemberId' => $member?->getId(),
                    'kcal' => 0.0, 'protein' => 0.0, 'fat' => 0.0, 'carbs' => 0.0,
                ];
                foreach (['kcal', 'protein', 'fat', 'carbs'] as $field) {
                    $summaries[$key][$field] = round($summaries[$key][$field] + $nutrition[$field], 1);
                }
            }
        }

        return $this->json(['entries' => $entries, 'summaries' => array_values($summaries)]);
    }

    /**
     * Атомарна заміна всіх записів дня для однієї людини (batch-збереження конструктора дня).
     */
    #[Route('/day', methods: ['PUT']),
        OA\Put(
            summary    : 'Атомарно замінити всі записи дня для однієї людини',
            description: 'В одній транзакції: видалити наявні записи дня → вставити нові. Так конструктор дня зберігається одним запитом.',
            requestBody: new OA\RequestBody(
                required: true,
                content : new OA\JsonContent(
                    required  : ['date', 'familyMemberId', 'entries'],
                    properties: [
                        new OA\Property(
                            property: 'date',
                            type    : 'string',
                            format  : 'date'
                        ),
                        new OA\Property(property: 'familyMemberId', type: 'integer'),
                        new OA\Property(
                            property: 'entries',
                            type    : 'array',
                            items   : new OA\Items(
                                properties: [
                                    new OA\Property(
                                        property: 'slot',
                                        type    : 'string',
                                        enum    : ['breakfast', 'lunch', 'dinner', 'snack', 'extra_snack']
                                    ),
                                    new OA\Property(
                                        property: 'dishId',
                                        type    : 'integer',
                                        nullable: true
                                    ),
                                    new OA\Property(
                                        property: 'ingredientId',
                                        type    : 'integer',
                                        nullable: true
                                    ),
                                    new OA\Property(
                                        property   : 'amount',
                                        type       : 'number',
                                        nullable   : true,
                                        description: 'Кількість продукту в його одиниці'
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            ),
            responses  : [
                new OA\Response(
                    response   : 200,
                    description: 'Збережено',
                    content    : new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'saved', type: 'integer'),
                        ]
                    )
                ),
                new OA\Response(
                    response   : 422,
                    description: 'Невалідні дані',
                    content    : new OA\JsonContent(ref: '#/components/schemas/Error')
                ),
            ]
        )]
    public function replaceDay(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => $this->translator->trans('error.invalid_json')], 400);
        }

        $date = $this->parseDate((string) ($data['date'] ?? ''));
        $member = $this->members->find((int) ($data['familyMemberId'] ?? 0));
        if ($date === null || $member === null || !is_array($data['entries'] ?? null)) {
            return $this->json(['error' => $this->translator->trans('error.meal_plan.day_payload')], 422);
        }

        $newEntries = [];
        foreach ($data['entries'] as $i => $item) {
            $slot = MealType::tryFrom((string) ($item['slot'] ?? ''));
            if ($slot === null) {
                return $this->json(['error' => $this->translator->trans('error.meal_plan.entry_unknown_slot', ['%num%' => $i + 1])], 422);
            }

            $entry = (new MealPlanEntry())
                ->setDate($date)
                ->setFamilyMember($member)
                ->setSlot($slot);

            $hasDish = !empty($item['dishId']);
            $hasIngredient = !empty($item['ingredientId']);
            if ($hasDish === $hasIngredient) {
                return $this->json(['error' => $this->translator->trans('error.meal_plan.entry_dish_or_ingredient', ['%num%' => $i + 1])], 422);
            }

            if ($hasDish) {
                $dish = $this->dishes->find((int) $item['dishId']);
                if ($dish === null) {
                    return $this->json(['error' => $this->translator->trans('error.meal_plan.entry_dish_not_found', ['%num%' => $i + 1])], 422);
                }
                $entry->setDish($dish);
            } else {
                $ingredient = $this->ingredients->find((int) $item['ingredientId']);
                $amount = (float) ($item['amount'] ?? 0);
                if ($ingredient === null || $amount <= 0) {
                    return $this->json(['error' => $this->translator->trans('error.meal_plan.entry_ingredient_amount', ['%num%' => $i + 1])], 422);
                }
                $entry->setIngredient($ingredient)->setAmount($amount);
            }

            $newEntries[] = $entry;
        }

        $this->em->wrapInTransaction(function () use ($date, $member, $newEntries): void {
            $this->em->createQuery(
                'DELETE FROM App\Entity\MealPlanEntry e WHERE e.date = :date AND e.familyMember = :member'
            )->execute(['date' => $date, 'member' => $member]);

            foreach ($newEntries as $entry) {
                $this->em->persist($entry);
            }
        });

        return $this->json(['saved' => count($newEntries)]);
    }

    #[Route('/entries', methods: ['POST']),
        OA\Post(
            summary    : 'Додати один запис у слот',
            description: 'Рівно одне з двох: dishId або ingredientId + amount.',
            requestBody: new OA\RequestBody(
                required: true,
                content : new OA\JsonContent(
                    required  : ['date', 'familyMemberId', 'slot'],
                    properties: [
                        new OA\Property(
                            property: 'date',
                            type    : 'string',
                            format  : 'date'
                        ),
                        new OA\Property(property: 'familyMemberId', type: 'integer'),
                        new OA\Property(
                            property: 'slot',
                            type    : 'string',
                            enum    : ['breakfast', 'lunch', 'dinner', 'snack', 'extra_snack']
                        ),
                        new OA\Property(
                            property: 'dishId',
                            type    : 'integer',
                            nullable: true
                        ),
                        new OA\Property(
                            property: 'ingredientId',
                            type    : 'integer',
                            nullable: true
                        ),
                        new OA\Property(
                            property: 'amount',
                            type    : 'number',
                            nullable: true
                        ),
                    ]
                )
            ),
            responses  : [
                new OA\Response(
                    response   : 201,
                    description: 'Створено',
                    content    : new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                        ]
                    )
                ),
                new OA\Response(
                    response   : 422,
                    description: 'Невалідні дані',
                    content    : new OA\JsonContent(ref: '#/components/schemas/Error')
                ),
            ]
        )]
    public function addEntry(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => $this->translator->trans('error.invalid_json')], 400);
        }

        $date = $this->parseDate((string) ($data['date'] ?? ''));
        $member = $this->members->find((int) ($data['familyMemberId'] ?? 0));
        $slot = MealType::tryFrom((string) ($data['slot'] ?? ''));

        if ($date === null || $member === null || $slot === null) {
            return $this->json(['error' => $this->translator->trans('error.meal_plan.add_payload')], 422);
        }

        $entry = (new MealPlanEntry())
            ->setDate($date)
            ->setFamilyMember($member)
            ->setSlot($slot);

        $hasDish = !empty($data['dishId']);
        $hasIngredient = !empty($data['ingredientId']);
        if ($hasDish === $hasIngredient) {
            return $this->json(['error' => $this->translator->trans('error.meal_plan.dish_or_ingredient')], 422);
        }

        if ($hasDish) {
            $dish = $this->dishes->find((int) $data['dishId']);
            if ($dish === null) {
                return $this->json(['error' => $this->translator->trans('error.meal_plan.dish_not_found')], 422);
            }
            $entry->setDish($dish);
        } else {
            $ingredient = $this->ingredients->find((int) $data['ingredientId']);
            $amount = (float) ($data['amount'] ?? 0);
            if ($ingredient === null || $amount <= 0) {
                return $this->json(['error' => $this->translator->trans('error.meal_plan.ingredient_amount')], 422);
            }
            $entry->setIngredient($ingredient)->setAmount($amount);
        }

        $this->em->persist($entry);
        $this->em->flush();

        return $this->json(['id' => $entry->getId()], 201);
    }

    #[Route('/entries/{id<\d+>}', methods: ['DELETE']),
        OA\Delete(
            summary  : 'Прибрати запис зі слота',
            responses: [
                new OA\Response(response: 204, description: 'Видалено'),
                new OA\Response(response: 404, description: 'Не знайдено'),
            ]
        )]
    public function deleteEntry(MealPlanEntry $entry): JsonResponse
    {
        $this->em->remove($entry);
        $this->em->flush();

        return $this->json(null, 204);
    }

    /**
     * Копіює всі записи діапазону [sourceFrom, sourceTo] у діапазон, що починається з targetFrom.
     * Цільовий діапазон заміняється, а не доповнюється — повторне копіювання не плодить дублів.
     * Опційний familyMemberId обмежує і джерело, і заміну однією людиною.
     */
    #[Route('/copy', methods: ['POST']),
        OA\Post(
            summary    : 'Скопіювати діапазон дат',
            description: 'Цільовий діапазон ЗАМІНЯЄТЬСЯ (не доповнюється) в транзакції — повторна копія не плодить дублів. Опційний familyMemberId обмежує і джерело, і заміну однією людиною. Діапазон — до 31 дня.',
            requestBody: new OA\RequestBody(
                required: true,
                content : new OA\JsonContent(
                    required  : ['sourceFrom', 'sourceTo', 'targetFrom'],
                    properties: [
                        new OA\Property(
                            property: 'sourceFrom',
                            type    : 'string',
                            format  : 'date'
                        ),
                        new OA\Property(
                            property: 'sourceTo',
                            type    : 'string',
                            format  : 'date'
                        ),
                        new OA\Property(
                            property: 'targetFrom',
                            type    : 'string',
                            format  : 'date'
                        ),
                        new OA\Property(
                            property: 'familyMemberId',
                            type    : 'integer',
                            nullable: true
                        ),
                    ]
                )
            ),
            responses  : [
                new OA\Response(
                    response   : 201,
                    description: 'Скопійовано',
                    content    : new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'copied', type: 'integer'),
                        ]
                    )
                ),
                new OA\Response(
                    response   : 422,
                    description: 'Невалідний діапазон',
                    content    : new OA\JsonContent(ref: '#/components/schemas/Error')
                ),
            ]
        )]
    public function copy(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => $this->translator->trans('error.invalid_json')], 400);
        }

        $sourceFrom = $this->parseDate((string) ($data['sourceFrom'] ?? ''));
        $sourceTo = $this->parseDate((string) ($data['sourceTo'] ?? ''));
        $targetFrom = $this->parseDate((string) ($data['targetFrom'] ?? ''));

        if ($sourceFrom === null || $sourceTo === null || $targetFrom === null || $sourceFrom > $sourceTo) {
            return $this->json(['error' => $this->translator->trans('error.meal_plan.copy_payload')], 422);
        }

        $rangeDays = (int) $sourceFrom->diff($sourceTo)->format('%a');
        if ($rangeDays + 1 > self::COPY_MAX_DAYS) {
            return $this->json(['error' => $this->translator->trans('error.meal_plan.copy_range', ['%days%' => self::COPY_MAX_DAYS])], 422);
        }

        $member = null;
        if (!empty($data['familyMemberId'])) {
            $member = $this->members->find((int) $data['familyMemberId']);
            if ($member === null) {
                return $this->json(['error' => $this->translator->trans('error.member_not_found')], 422);
            }
        }

        $sourceQb = $this->entries->createQueryBuilder('e')
            ->where('e.date >= :from AND e.date <= :to')
            ->setParameter('from', $sourceFrom)
            ->setParameter('to', $sourceTo);
        if ($member !== null) {
            $sourceQb->andWhere('e.familyMember = :member')->setParameter('member', $member);
        }
        $source = $sourceQb->getQuery()->getResult();

        $targetTo = $targetFrom->modify(sprintf('+%d days', $rangeDays));

        $copied = 0;
        $this->em->wrapInTransaction(function () use ($source, $sourceFrom, $targetFrom, $targetTo, $member, &$copied): void {
            $delete = $this->em->createQueryBuilder()
                ->delete(MealPlanEntry::class, 'e')
                ->where('e.date >= :from AND e.date <= :to')
                ->setParameter('from', $targetFrom)
                ->setParameter('to', $targetTo);
            if ($member !== null) {
                $delete->andWhere('e.familyMember = :member')->setParameter('member', $member);
            }
            $delete->getQuery()->execute();

            foreach ($source as $entry) {
                /** @var MealPlanEntry $entry */
                $entryDate = $entry->getDate();
                if ($entryDate === null) {
                    continue;
                }
                $offsetDays = (int) $sourceFrom->diff($entryDate)->format('%a');
                $copy = (new MealPlanEntry())
                    ->setDate($targetFrom->modify(sprintf('+%d days', $offsetDays)))
                    ->setFamilyMember($entry->getFamilyMember())
                    ->setDish($entry->getDish())
                    ->setIngredient($entry->getIngredient())
                    ->setAmount($entry->getAmount())
                    ->setSlot($entry->getSlot());
                $this->em->persist($copy);
                ++$copied;
            }
        });

        return $this->json(['copied' => $copied], 201);
    }

    /** @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable} */
    private function parseRange(Request $request): array
    {
        return [
            $this->parseDate((string) $request->query->get('from', '')),
            $this->parseDate((string) $request->query->get('to', '')),
        ];
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false ? null : $date;
    }
}

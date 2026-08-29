<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Ingredient;
use App\Enum\IngredientCategory;
use App\Enum\Unit;
use App\Repository\IngredientRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/ingredients')]
#[OA\Tag(name: 'Ingredients', description: 'Довідник інгредієнтів із КБЖВ на 100 г (~7 200 продуктів USDA + власні)')]
class IngredientController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', methods: ['GET']),
        OA\Get(
            summary   : 'Пошук інгредієнтів',
            parameters: [
                new OA\Parameter(
                    name       : 'search',
                    in         : 'query',
                    description: 'Підрядок назви (укр або англ)',
                    schema     : new OA\Schema(type: 'string')
                ),
                new OA\Parameter(
                    name  : 'category',
                    in    : 'query',
                    schema: new OA\Schema(type: 'string')
                ),
                new OA\Parameter(
                    name       : 'limit',
                    in         : 'query',
                    description: '1–1000, типово 100',
                    schema     : new OA\Schema(type: 'integer')
                ),
            ],
            responses : [
                new OA\Response(
                    response   : 200,
                    description: 'Список',
                    content    : new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Ingredient'))
                ),
            ]
        )]
    public function list(Request $request, IngredientRepository $repository): JsonResponse
    {
        $qb = $repository->createQueryBuilder('i')->orderBy('i.name', 'ASC');

        $search = trim((string) $request->query->get('search', ''));
        if ($search !== '') {
            $qb->andWhere('LOWER(i.name) LIKE LOWER(:search) OR LOWER(i.nameEn) LIKE LOWER(:search)')
                ->setParameter('search', '%'.$search.'%');
        }

        $category = $request->query->get('category');
        if ($category !== null && $category !== '') {
            $qb->andWhere('i.category = :category')->setParameter('category', $category);
        }

        $limit = min(max($request->query->getInt('limit', 100), 1), 1000);
        $qb->setMaxResults($limit);

        return $this->json(array_map($this->format(...), $qb->getQuery()->getResult()));
    }

    /**
     * Повний довідник для клієнтського кешу (Pinia store) — локальний пошук без запитів.
     * Сирий DBAL замість ORM: гідрація 7+ тис. entity займала секунди.
     */
    #[Route('/all', methods: ['GET']),
        OA\Get(
            summary    : 'Повний довідник для клієнтського кешу',
            description: 'Сирий DBAL + json_encode повз Serializer — 7 000+ рядків за мілісекунди.',
            responses  : [
                new OA\Response(
                    response   : 200,
                    description: 'Усі інгредієнти',
                    content    : new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Ingredient'))
                ),
            ]
        )]
    public function all(): JsonResponse
    {
        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT id, name, name_en, category, unit, kcal_per100, protein_per100, fat_per100, carbs_per100, piece_weight_grams
             FROM ingredient ORDER BY name'
        );

        $items = array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'nameEn' => $r['name_en'],
            'category' => $r['category'],
            'unit' => $r['unit'],
            'kcalPer100' => (float) $r['kcal_per100'],
            'proteinPer100' => (float) $r['protein_per100'],
            'fatPer100' => (float) $r['fat_per100'],
            'carbsPer100' => (float) $r['carbs_per100'],
            'pieceWeightGrams' => $r['piece_weight_grams'] !== null ? (float) $r['piece_weight_grams'] : null,
        ], $rows);

        // Обхід Symfony Serializer: на 7+ тис. рядків він додає секунди, plain json_encode — мілісекунди.
        return new JsonResponse(json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 200, [], true);
    }

    #[Route('/{id<\d+>}', methods: ['GET']),
        OA\Get(
            summary  : 'Один інгредієнт',
            responses: [
                new OA\Response(
                    response   : 200,
                    description: 'OK',
                    content    : new OA\JsonContent(ref: '#/components/schemas/Ingredient')
                ),
                new OA\Response(response: 404, description: 'Не знайдено'),
            ]
        )]
    public function show(Ingredient $ingredient): JsonResponse
    {
        return $this->json($this->format($ingredient));
    }

    #[Route('', methods: ['POST']),
        OA\Post(
            summary    : 'Створити інгредієнт',
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/IngredientInput')),
            responses  : [
                new OA\Response(
                    response   : 201,
                    description: 'Створено',
                    content    : new OA\JsonContent(ref: '#/components/schemas/Ingredient')
                ),
                new OA\Response(
                    response   : 422,
                    description: 'Невалідні дані',
                    content    : new OA\JsonContent(ref: '#/components/schemas/FieldErrors')
                ),
            ]
        )]
    public function create(Request $request): JsonResponse
    {
        $ingredient = new Ingredient();

        return $this->apply($ingredient, $request, 201);
    }

    #[Route('/{id<\d+>}', methods: ['PUT']),
        OA\Put(
            summary    : 'Оновити інгредієнт',
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/IngredientInput')),
            responses  : [
                new OA\Response(
                    response   : 200,
                    description: 'Оновлено',
                    content    : new OA\JsonContent(ref: '#/components/schemas/Ingredient')
                ),
                new OA\Response(
                    response   : 422,
                    description: 'Невалідні дані',
                    content    : new OA\JsonContent(ref: '#/components/schemas/FieldErrors')
                ),
            ]
        )]
    public function update(Ingredient $ingredient, Request $request): JsonResponse
    {
        return $this->apply($ingredient, $request, 200);
    }

    #[Route('/{id<\d+>}', methods: ['DELETE']),
        OA\Delete(
            summary    : 'Видалити інгредієнт',
            description: 'Інгредієнт, що використовується у стравах, видалити не можна.',
            responses  : [
                new OA\Response(response: 204, description: 'Видалено'),
                new OA\Response(
                    response   : 409,
                    description: 'Використовується у стравах',
                    content    : new OA\JsonContent(ref: '#/components/schemas/Error')
                ),
            ]
        )]
    public function delete(Ingredient $ingredient): JsonResponse
    {
        $usages = $this->em->createQuery(
            'SELECT COUNT(dpi.id) FROM App\Entity\DishPortionIngredient dpi WHERE dpi.ingredient = :ingredient'
        )->setParameter('ingredient', $ingredient)->getSingleScalarResult();

        if ($usages > 0) {
            return $this->json(['error' => $this->translator->trans('error.ingredient.used_in_dishes', ['%count%' => $usages])], 409);
        }

        $this->em->remove($ingredient);
        $this->em->flush();

        return $this->json(null, 204);
    }

    private function apply(Ingredient $ingredient, Request $request, int $successStatus): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => $this->translator->trans('error.invalid_json')], 400);
        }

        $category = IngredientCategory::tryFrom((string) ($data['category'] ?? ''));
        $unit = Unit::tryFrom((string) ($data['unit'] ?? ''));
        if ($category === null || $unit === null) {
            return $this->json(['error' => $this->translator->trans('error.ingredient.unknown_category_or_unit')], 422);
        }

        $ingredient
            ->setName(trim((string) ($data['name'] ?? '')))
            ->setCategory($category)
            ->setUnit($unit)
            ->setKcalPer100((float) ($data['kcalPer100'] ?? 0))
            ->setProteinPer100((float) ($data['proteinPer100'] ?? 0))
            ->setFatPer100((float) ($data['fatPer100'] ?? 0))
            ->setCarbsPer100((float) ($data['carbsPer100'] ?? 0))
            ->setPieceWeightGrams(isset($data['pieceWeightGrams']) && $data['pieceWeightGrams'] !== '' ? (float) $data['pieceWeightGrams'] : null);

        if ($ingredient->getUnit() === Unit::Pieces && $ingredient->getPieceWeightGrams() === null) {
            return $this->json(['errors' => ['pieceWeightGrams' => $this->translator->trans('error.ingredient.piece_weight_required')]], 422);
        }

        $violations = $this->validator->validate($ingredient);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json(['errors' => $errors], 422);
        }

        $this->em->persist($ingredient);
        $this->em->flush();

        return $this->json($this->format($ingredient), $successStatus);
    }

    /** @return array<string, mixed> */
    private function format(Ingredient $i): array
    {
        return [
            'id' => $i->getId(),
            'name' => $i->getName(),
            'nameEn' => $i->getNameEn(),
            'category' => $i->getCategory()->value,
            'unit' => $i->getUnit()->value,
            'kcalPer100' => $i->getKcalPer100(),
            'proteinPer100' => $i->getProteinPer100(),
            'fatPer100' => $i->getFatPer100(),
            'carbsPer100' => $i->getCarbsPer100(),
            'pieceWeightGrams' => $i->getPieceWeightGrams(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Dish;
use App\Entity\DishPortion;
use App\Entity\DishPortionIngredient;
use App\Enum\MealType;
use App\Repository\DishRepository;
use App\Repository\FamilyMemberRepository;
use App\Repository\IngredientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/dishes')]
class DishController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly FamilyMemberRepository $members,
        private readonly IngredientRepository $ingredients,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request, DishRepository $repository): JsonResponse
    {
        $qb = $repository->createQueryBuilder('d')
            ->leftJoin('d.portions', 'p')->addSelect('p')
            ->leftJoin('p.ingredients', 'pi')->addSelect('pi')
            ->leftJoin('pi.ingredient', 'i')->addSelect('i')
            ->orderBy('d.name', 'ASC');

        $search = trim((string) $request->query->get('search', ''));
        if ($search !== '') {
            $qb->andWhere('LOWER(d.name) LIKE LOWER(:search) OR LOWER(d.code) LIKE LOWER(:search)')
                ->setParameter('search', '%'.$search.'%');
        }

        $category = $request->query->get('category');
        if ($category !== null && $category !== '') {
            $qb->andWhere('d.category = :category')->setParameter('category', $category);
        }

        return $this->json(array_map($this->format(...), $qb->getQuery()->getResult()));
    }

    #[Route('/{id<\d+>}', methods: ['GET'])]
    public function show(Dish $dish): JsonResponse
    {
        return $this->json($this->format($dish));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        return $this->apply(new Dish(), $request, 201);
    }

    #[Route('/{id<\d+>}', methods: ['PUT'])]
    public function update(Dish $dish, Request $request): JsonResponse
    {
        return $this->apply($dish, $request, 200);
    }

    #[Route('/{id<\d+>}', methods: ['DELETE'])]
    public function delete(Dish $dish): JsonResponse
    {
        $usages = $this->em->createQuery(
            'SELECT COUNT(e.id) FROM App\Entity\MealPlanEntry e WHERE e.dish = :dish'
        )->setParameter('dish', $dish)->getSingleScalarResult();

        if ($usages > 0) {
            return $this->json(['error' => $this->translator->trans('error.dish.planned_in_menu', ['%count%' => $usages])], 409);
        }

        $this->em->remove($dish);
        $this->em->flush();

        return $this->json(null, 204);
    }

    private function apply(Dish $dish, Request $request, int $successStatus): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => $this->translator->trans('error.invalid_json')], 400);
        }

        $category = MealType::tryFrom((string) ($data['category'] ?? ''));
        if ($category === null) {
            return $this->json(['error' => $this->translator->trans('error.dish.unknown_category')], 422);
        }

        $code = isset($data['code']) && trim((string) $data['code']) !== '' ? trim((string) $data['code']) : null;

        $dish
            ->setName(trim((string) ($data['name'] ?? '')))
            ->setCode($code)
            ->setCategory($category)
            ->setRecipe(isset($data['recipe']) && trim((string) $data['recipe']) !== '' ? (string) $data['recipe'] : null)
            ->setYoutubeUrl(isset($data['youtubeUrl']) && trim((string) $data['youtubeUrl']) !== '' ? trim((string) $data['youtubeUrl']) : null)
            ->setBatchCooking((bool) ($data['batchCooking'] ?? false));

        // Порції перебудовуємо з нуля — orphanRemoval прибере старі рядки.
        foreach ($dish->getPortions()->toArray() as $existing) {
            $dish->removePortion($existing);
        }

        foreach ($data['portions'] ?? [] as $portionData) {
            $member = $this->members->find((int) ($portionData['familyMemberId'] ?? 0));
            if ($member === null) {
                return $this->json(['error' => $this->translator->trans('error.dish.unknown_member')], 422);
            }

            $portion = (new DishPortion())->setFamilyMember($member);
            foreach ($portionData['ingredients'] ?? [] as $item) {
                $ingredient = $this->ingredients->find((int) ($item['ingredientId'] ?? 0));
                if ($ingredient === null) {
                    return $this->json(['error' => $this->translator->trans('error.dish.unknown_ingredient')], 422);
                }
                $amount = (float) ($item['amount'] ?? 0);
                if ($amount <= 0) {
                    return $this->json(['errors' => ['portions' => $this->translator->trans('error.dish.amount_positive')]], 422);
                }
                $portion->addIngredient(
                    (new DishPortionIngredient())->setIngredient($ingredient)->setAmount($amount)
                );
            }
            $dish->addPortion($portion);
        }

        $memberIds = array_map(
            static fn (DishPortion $p): ?int => $p->getFamilyMember()?->getId(),
            $dish->getPortions()->toArray()
        );
        if (count($memberIds) !== count(array_unique($memberIds))) {
            return $this->json(['error' => $this->translator->trans('error.dish.one_portion_per_member')], 422);
        }

        $violations = $this->validator->validate($dish);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json(['errors' => $errors], 422);
        }

        $this->em->persist($dish);
        $this->em->flush();

        return $this->json($this->format($dish), $successStatus);
    }

    /** @return array<string, mixed> */
    private function format(Dish $dish): array
    {
        return [
            'id' => $dish->getId(),
            'code' => $dish->getCode(),
            'name' => $dish->getName(),
            'category' => $dish->getCategory()->value,
            'recipe' => $dish->getRecipe(),
            'youtubeUrl' => $dish->getYoutubeUrl(),
            'batchCooking' => $dish->isBatchCooking(),
            'portions' => array_map(
                static fn (DishPortion $p): array => [
                    'id' => $p->getId(),
                    'familyMemberId' => $p->getFamilyMember()?->getId(),
                    'nutrition' => $p->calculateNutrition(),
                    'ingredients' => array_map(
                        static fn (DishPortionIngredient $pi): array => [
                            'ingredientId' => $pi->getIngredient()?->getId(),
                            'name' => $pi->getIngredient()?->getName(),
                            'unit' => $pi->getIngredient()?->getUnit()->value,
                            'amount' => $pi->getAmount(),
                            'kcalPer100' => $pi->getIngredient()?->getKcalPer100(),
                            'proteinPer100' => $pi->getIngredient()?->getProteinPer100(),
                            'fatPer100' => $pi->getIngredient()?->getFatPer100(),
                            'carbsPer100' => $pi->getIngredient()?->getCarbsPer100(),
                            'pieceWeightGrams' => $pi->getIngredient()?->getPieceWeightGrams(),
                        ],
                        $p->getIngredients()->toArray()
                    ),
                ],
                $dish->getPortions()->toArray()
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\MealPlanEntry;
use App\Repository\MealPlanEntryRepository;
use App\Shopping\ShoppingListBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/shopping-list')]
class ShoppingListController extends AbstractController
{
    /** Захист від випадкового «за весь рік» — список на квартал уже безглуздий. */
    private const MAX_DAYS = 92;

    public function __construct(
        private readonly MealPlanEntryRepository $entries,
        private readonly ShoppingListBuilder $builder,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $from = $this->parseDate((string) $request->query->get('from', ''));
        $to = $this->parseDate((string) $request->query->get('to', ''));
        if ($from === null || $to === null || $from > $to) {
            return $this->json(['error' => $this->translator->trans('error.meal_plan.from_to_required')], 400);
        }
        if ((int) $from->diff($to)->format('%a') + 1 > self::MAX_DAYS) {
            return $this->json(['error' => $this->translator->trans('error.shopping.range_too_long', ['%days%' => self::MAX_DAYS])], 422);
        }

        /** @var MealPlanEntry[] $result */
        $result = $this->entries->createQueryBuilder('e')
            ->leftJoin('e.dish', 'd')->addSelect('d')
            ->leftJoin('d.portions', 'p')->addSelect('p')
            ->leftJoin('p.ingredients', 'pi')->addSelect('pi')
            ->leftJoin('pi.ingredient', 'i')->addSelect('i')
            ->leftJoin('e.ingredient', 'ei')->addSelect('ei')
            ->where('e.date >= :from AND e.date <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()->getResult();

        return $this->json([
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'entries' => count($result),
            'groups' => $this->builder->build($result),
        ]);
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false ? null : $date;
    }
}

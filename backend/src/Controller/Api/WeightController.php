<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\WeightEntry;
use App\Repository\FamilyMemberRepository;
use App\Repository\WeightEntryRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/weight-entries')]
class WeightController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly WeightEntryRepository $entries,
        private readonly FamilyMemberRepository $members,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->entries->createQueryBuilder('w')->orderBy('w.date', 'ASC');

        $memberId = $request->query->getInt('memberId');
        if ($memberId > 0) {
            $qb->andWhere('w.familyMember = :member')->setParameter('member', $memberId);
        }

        return $this->json(array_map(
            static fn (WeightEntry $w): array => [
                'id' => $w->getId(),
                'familyMemberId' => $w->getFamilyMember()?->getId(),
                'date' => $w->getDate()?->format('Y-m-d'),
                'weightKg' => $w->getWeightKg(),
            ],
            $qb->getQuery()->getResult()
        ));
    }

    /**
     * Upsert: якщо на цю дату для цієї людини вже є запис — оновлюємо вагу.
     */
    #[Route('', methods: ['POST'])]
    public function save(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => 'Невалідний JSON'], 400);
        }

        $member = $this->members->find((int) ($data['familyMemberId'] ?? 0));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($data['date'] ?? ''));
        $weight = (float) ($data['weightKg'] ?? 0);

        if ($member === null || $date === false) {
            return $this->json(['error' => 'Потрібні коректні familyMemberId і date'], 422);
        }

        $entry = $this->entries->findOneBy(['familyMember' => $member, 'date' => $date])
            ?? (new WeightEntry())->setFamilyMember($member)->setDate($date);
        $entry->setWeightKg($weight);

        $violations = $this->validator->validate($entry);
        if (count($violations) > 0) {
            return $this->json(['errors' => ['weightKg' => 'Вага має бути в межах 20-400 кг']], 422);
        }

        $isNew = $entry->getId() === null;
        $this->em->persist($entry);
        $this->em->flush();

        return $this->json(['id' => $entry->getId()], $isNew ? 201 : 200);
    }

    #[Route('/{id<\d+>}', methods: ['DELETE'])]
    public function delete(WeightEntry $entry): JsonResponse
    {
        $this->em->remove($entry);
        $this->em->flush();

        return $this->json(null, 204);
    }

    /**
     * Середня вага за ISO-тижнями — план радить порівнювати тижневі середні, а не окремі дні.
     */
    #[Route('/weekly', methods: ['GET'])]
    public function weekly(Request $request, Connection $connection): JsonResponse
    {
        $sql = <<<'SQL'
            SELECT family_member_id,
                   date_trunc('week', date)::date AS week_start,
                   round(avg(weight_kg)::numeric, 2) AS avg_weight,
                   count(*) AS measurements
            FROM weight_entry
            WHERE (:memberId = 0 OR family_member_id = :memberId)
            GROUP BY family_member_id, week_start
            ORDER BY family_member_id, week_start
            SQL;

        $rows = $connection->fetchAllAssociative($sql, ['memberId' => $request->query->getInt('memberId')]);

        return $this->json(array_map(
            static fn (array $row): array => [
                'familyMemberId' => (int) $row['family_member_id'],
                'weekStart' => $row['week_start'],
                'avgWeight' => (float) $row['avg_weight'],
                'measurements' => (int) $row['measurements'],
            ],
            $rows
        ));
    }
}

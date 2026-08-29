<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\HealthEvent;
use App\Health\HealthEventTypeRegistry;
use App\Repository\FamilyMemberRepository;
use App\Repository\HealthEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/health-events')]
#[OA\Tag(name: 'Health journal', description: "Події здоров'я з JSONB payload: тиск/пульс, вага, болі з тяжкістю, ліки, нотатки, власні. Поля валідує реєстр типів")]
class HealthEventController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HealthEventRepository $events,
        private readonly FamilyMemberRepository $members,
        private readonly HealthEventTypeRegistry $types,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', methods: ['GET']),
        OA\Get(
            summary   : 'Події за фільтрами',
            parameters: [
                new OA\Parameter(
                    name  : 'memberId',
                    in    : 'query',
                    schema: new OA\Schema(type: 'integer')
                ),
                new OA\Parameter(
                    name  : 'type',
                    in    : 'query',
                    schema: new OA\Schema(type: 'string')
                ),
                new OA\Parameter(
                    name  : 'from',
                    in    : 'query',
                    schema: new OA\Schema(type: 'string', format: 'date')
                ),
                new OA\Parameter(
                    name  : 'to',
                    in    : 'query',
                    schema: new OA\Schema(type: 'string', format: 'date')
                ),
            ],
            responses : [
                new OA\Response(
                    response   : 200,
                    description: 'Хронологічно (дата, час)',
                    content    : new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/HealthEvent'))
                ),
            ]
        )]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->events->createQueryBuilder('e')
            ->orderBy('e.date', 'ASC')
            ->addOrderBy('e.time', 'ASC')
            ->addOrderBy('e.id', 'ASC');

        $memberId = $request->query->getInt('memberId');
        if ($memberId > 0) {
            $qb->andWhere('e.familyMember = :member')->setParameter('member', $memberId);
        }

        $type = (string) $request->query->get('type', '');
        if ($type !== '') {
            $qb->andWhere('e.type = :type')->setParameter('type', $type);
        }

        $from = $this->parseDate((string) $request->query->get('from', ''));
        if ($from !== null) {
            $qb->andWhere('e.date >= :from')->setParameter('from', $from);
        }
        $to = $this->parseDate((string) $request->query->get('to', ''));
        if ($to !== null) {
            $qb->andWhere('e.date <= :to')->setParameter('to', $to);
        }

        return $this->json(array_map($this->format(...), $qb->getQuery()->getResult()));
    }

    #[Route('', methods: ['POST']),
        OA\Post(
            summary    : 'Створити подію',
            requestBody: new OA\RequestBody(
                required: true,
                content : new OA\JsonContent(ref: '#/components/schemas/HealthEventInput')
            ),
            responses  : [
                new OA\Response(
                    response   : 201,
                    description: 'Створено',
                    content    : new OA\JsonContent(ref: '#/components/schemas/HealthEvent')
                ),
                new OA\Response(
                    response   : 422,
                    description: 'Невалідні дані типу',
                    content    : new OA\JsonContent(ref: '#/components/schemas/FieldErrors')
                ),
            ]
        )]
    public function create(Request $request): JsonResponse
    {
        return $this->apply(new HealthEvent(), $request, 201);
    }

    #[Route('/{id<\d+>}', methods: ['PUT']),
        OA\Put(
            summary    : 'Оновити подію',
            requestBody: new OA\RequestBody(
                required: true,
                content : new OA\JsonContent(ref: '#/components/schemas/HealthEventInput')
            ),
            responses  : [
                new OA\Response(
                    response   : 200,
                    description: 'Оновлено',
                    content    : new OA\JsonContent(ref: '#/components/schemas/HealthEvent')
                ),
                new OA\Response(
                    response   : 422,
                    description: 'Невалідні дані',
                    content    : new OA\JsonContent(ref: '#/components/schemas/FieldErrors')
                ),
            ]
        )]
    public function update(HealthEvent $event, Request $request): JsonResponse
    {
        return $this->apply($event, $request, 200);
    }

    #[Route('/{id<\d+>}', methods: ['DELETE']),
        OA\Delete(
            summary  : 'Видалити подію',
            responses: [
                new OA\Response(response: 204, description: 'Видалено'),
                new OA\Response(response: 404, description: 'Не знайдено'),
            ]
        )]
    public function delete(HealthEvent $event): JsonResponse
    {
        $this->em->remove($event);
        $this->em->flush();

        return $this->json(null, 204);
    }

    private function apply(HealthEvent $event, Request $request, int $successStatus): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => $this->translator->trans('error.invalid_json')], 400);
        }

        $member = $this->members->find((int) ($data['familyMemberId'] ?? 0));
        $date = $this->parseDate((string) ($data['date'] ?? ''));
        $type = (string) ($data['type'] ?? '');

        if ($member === null || $date === null || !$this->types->isKnown($type)) {
            return $this->json(['error' => $this->translator->trans('error.health.payload_required')], 422);
        }

        $time = null;
        $rawTime = trim((string) ($data['time'] ?? ''));
        if ($rawTime !== '') {
            // createFromFormat поблажливий (25:70 → 02:10 наступної доби), тому спершу суворий формат
            $parsed = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $rawTime) === 1
                ? \DateTimeImmutable::createFromFormat('!H:i', $rawTime)
                : false;
            if ($parsed === false) {
                return $this->json(['errors' => ['time' => $this->translator->trans('error.health.time_format')]], 422);
            }
            $time = $parsed;
        }

        $rawPayload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        ['errors' => $errors, 'payload' => $payload] = $this->types->validate($type, $rawPayload);
        if ($errors !== []) {
            return $this->json(['errors' => $errors], 422);
        }

        $note = trim((string) ($data['note'] ?? ''));

        $event
            ->setFamilyMember($member)
            ->setDate($date)
            ->setTime($time)
            ->setType($type)
            ->setPayload($payload)
            ->setNote($note === '' ? null : mb_substr($note, 0, 2000));

        $this->em->persist($event);
        $this->em->flush();

        return $this->json($this->format($event), $successStatus);
    }

    /** @return array<string, mixed> */
    private function format(HealthEvent $e): array
    {
        return [
            'id' => $e->getId(),
            'familyMemberId' => $e->getFamilyMember()?->getId(),
            'date' => $e->getDate()?->format('Y-m-d'),
            'time' => $e->getTime()?->format('H:i'),
            'type' => $e->getType(),
            'payload' => $e->getPayload() === [] ? new \stdClass() : $e->getPayload(),
            'note' => $e->getNote(),
        ];
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false ? null : $date;
    }
}

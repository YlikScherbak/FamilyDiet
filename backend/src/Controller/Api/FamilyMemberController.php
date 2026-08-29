<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\FamilyMember;
use App\Repository\FamilyMemberRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/family-members')]
#[OA\Tag(name: 'Family', description: "Члени сім'ї з цілями ккал/БЖВ")]
class FamilyMemberController extends AbstractController
{
    #[Route('', methods: ['GET']),
        OA\Get(
            summary  : "Усі члени сім'ї",
            responses: [
                new OA\Response(
                    response   : 200,
                    description: 'OK',
                    content    : new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/FamilyMember'))
                ),
            ]
        )]
    public function list(FamilyMemberRepository $repository): JsonResponse
    {
        return $this->json(array_map(
            static fn (FamilyMember $m): array => [
                'id' => $m->getId(),
                'name' => $m->getName(),
                'kcalTarget' => $m->getKcalTarget(),
                'proteinTarget' => $m->getProteinTarget(),
                'fatTarget' => $m->getFatTarget(),
                'carbsTarget' => $m->getCarbsTarget(),
            ],
            $repository->findBy([], ['id' => 'ASC'])
        ));
    }
}

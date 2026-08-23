<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\FamilyMember;
use App\Repository\FamilyMemberRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/family-members')]
class FamilyMemberController extends AbstractController
{
    #[Route('', methods: ['GET'])]
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

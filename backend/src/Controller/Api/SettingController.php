<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\AppSetting;
use App\Repository\AppSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/settings')]
class SettingController extends AbstractController
{
    /** Захист від сміття: значення налаштування — не більше 16 КБ у JSON. */
    private const MAX_VALUE_BYTES = 16384;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AppSettingRepository $settings,
    ) {
    }

    #[Route('/{key<[a-z0-9_-]{1,50}>}', methods: ['GET'])]
    public function show(string $key): JsonResponse
    {
        $setting = $this->settings->find($key);

        return $this->json($setting?->getValue() ?? new \stdClass());
    }

    #[Route('/{key<[a-z0-9_-]{1,50}>}', methods: ['PUT'])]
    public function save(string $key, Request $request): JsonResponse
    {
        try {
            $value = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => 'Невалідний JSON'], 400);
        }

        if (strlen((string) json_encode($value)) > self::MAX_VALUE_BYTES) {
            return $this->json(['error' => 'Налаштування завелике'], 422);
        }

        $setting = $this->settings->find($key) ?? (new AppSetting())->setKey($key);
        $setting->setValue($value);

        $this->em->persist($setting);
        $this->em->flush();

        return $this->json($setting->getValue() === [] ? new \stdClass() : $setting->getValue());
    }
}

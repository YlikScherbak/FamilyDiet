<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\AppSetting;
use App\Repository\AppSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/settings')]
#[OA\Tag(name: 'Settings', description: "Іменовані JSONB-налаштування без прив'язки до сутностей: вигляд графіка, пресети, позначки закупівель")]
class SettingController extends AbstractController
{
    /** Захист від сміття: значення налаштування — не більше 16 КБ у JSON. */
    private const MAX_VALUE_BYTES = 16384;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AppSettingRepository $settings,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/{key<[a-z0-9_-]{1,50}>}', methods: ['GET']),
        OA\Get(
            summary   : 'Прочитати налаштування',
            parameters: [
                new OA\Parameter(
                    name       : 'key',
                    in         : 'path',
                    required   : true,
                    description: '[a-z0-9_-]{1,50}, напр. health_chart',
                    schema     : new OA\Schema(type: 'string')
                ),
            ],
            responses : [
                new OA\Response(
                    response   : 200,
                    description: 'Значення або {} якщо ключа ще нема',
                    content    : new OA\JsonContent(type: 'object')
                ),
            ]
        )]
    public function show(string $key): JsonResponse
    {
        $setting = $this->settings->find($key);

        return $this->json($setting?->getValue() ?? new \stdClass());
    }

    #[Route('/{key<[a-z0-9_-]{1,50}>}', methods: ['PUT']),
        OA\Put(
            summary    : 'Записати налаштування (перезапис цілком)',
            parameters : [
                new OA\Parameter(
                    name    : 'key',
                    in      : 'path',
                    required: true,
                    schema  : new OA\Schema(type: 'string')
                ),
            ],
            requestBody: new OA\RequestBody(
                required   : true,
                description: 'Довільний JSON-обʼєкт до 16 КБ',
                content    : new OA\JsonContent(type: 'object')
            ),
            responses  : [
                new OA\Response(
                    response   : 200,
                    description: 'Збережене значення',
                    content    : new OA\JsonContent(type: 'object')
                ),
                new OA\Response(
                    response   : 422,
                    description: 'Завелике',
                    content    : new OA\JsonContent(ref: '#/components/schemas/Error')
                ),
            ]
        )]
    public function save(string $key, Request $request): JsonResponse
    {
        try {
            $value = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => $this->translator->trans('error.invalid_json')], 400);
        }

        if (strlen((string) json_encode($value)) > self::MAX_VALUE_BYTES) {
            return $this->json(['error' => $this->translator->trans('error.setting.too_large')], 422);
        }

        $setting = $this->settings->find($key) ?? (new AppSetting())->setKey($key);
        $setting->setValue($value);

        $this->em->persist($setting);
        $this->em->flush();

        return $this->json($setting->getValue() === [] ? new \stdClass() : $setting->getValue());
    }
}

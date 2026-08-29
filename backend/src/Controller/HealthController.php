<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController
{
    #[OA\Tag(name: 'System')]
    #[Route('/api/health', methods: ['GET']),
        OA\Get(
            summary  : 'Healthcheck застосунку і БД',
            responses: [
                new OA\Response(
                    response   : 200,
                    description: 'OK',
                    content    : new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: 'status',
                                type    : 'string',
                                example : 'ok'
                            ),
                            new OA\Property(property: 'db', type: 'boolean'),
                        ]
                    )
                ),
            ]
        )]
    public function __invoke(Connection $connection): JsonResponse
    {
        $dbOk = true;

        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $dbOk = false;
        }

        return new JsonResponse([
            'status' => $dbOk ? 'ok' : 'degraded',
            'db' => $dbOk,
        ], $dbOk ? 200 : 503);
    }
}

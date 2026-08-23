<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController
{
    #[Route('/api/health', methods: ['GET'])]
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

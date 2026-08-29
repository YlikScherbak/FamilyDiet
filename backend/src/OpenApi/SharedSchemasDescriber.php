<?php

declare(strict_types=1);

namespace App\OpenApi;

use Nelmio\ApiDocBundle\Describer\DescriberInterface;
use OpenApi\Annotations as OA;
use OpenApi\Generator;

/**
 * NelmioApiDocBundle читає атрибути лише з контролерів, тож спільні схеми з Schemas.php
 * сюди б не потрапили. Цей describer сканує каталог src/OpenApi swagger-php і
 * додає знайдені components.schemas у документ.
 */
final class SharedSchemasDescriber implements DescriberInterface
{
    public function describe(OA\OpenApi $api): void
    {
        $scanned = (new Generator())->generate([__DIR__.'/Schemas.php'], validate: false);
        if ($scanned === null || Generator::isDefault($scanned->components) || Generator::isDefault($scanned->components->schemas)) {
            return;
        }

        if (Generator::isDefault($api->components)) {
            $api->components = new OA\Components(['_context' => $api->_context]);
        }
        $existing = Generator::isDefault($api->components->schemas) ? [] : $api->components->schemas;
        $known = array_map(static fn (OA\Schema $s): string => $s->schema, $existing);

        // Describer може бути викликаний повторно в межах одного запиту — не дублюємо схеми.
        foreach ($scanned->components->schemas as $schema) {
            if (!in_array($schema->schema, $known, true)) {
                // Інакше процесор MergeIntoComponents вважає схему top-level і вливає її вдруге.
                $schema->_context->nested = $api->components;
                $existing[] = $schema;
            }
        }

        $api->components->schemas = $existing;
    }
}

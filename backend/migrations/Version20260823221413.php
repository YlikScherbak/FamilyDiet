<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * app_setting: іменовані JSONB-налаштування (перший споживач — налаштування
 * графіка здоровʼя: кольори і стилі відображення типів подій).
 */
final class Version20260823221413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'app_setting: key/value (JSONB) налаштування застосунку';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_setting (
            key VARCHAR(50) NOT NULL,
            value JSONB NOT NULL,
            PRIMARY KEY(key)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_setting');
    }
}

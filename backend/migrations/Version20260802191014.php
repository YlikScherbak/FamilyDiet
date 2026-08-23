<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802191014 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE meal_plan_entry ADD amount DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE meal_plan_entry ADD ingredient_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE meal_plan_entry ALTER dish_id DROP NOT NULL');
        $this->addSql('ALTER TABLE meal_plan_entry ADD CONSTRAINT FK_EFEB6E06933FE08C FOREIGN KEY (ingredient_id) REFERENCES ingredient (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_EFEB6E06933FE08C ON meal_plan_entry (ingredient_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE meal_plan_entry DROP CONSTRAINT FK_EFEB6E06933FE08C');
        $this->addSql('DROP INDEX IDX_EFEB6E06933FE08C');
        $this->addSql('ALTER TABLE meal_plan_entry DROP amount');
        $this->addSql('ALTER TABLE meal_plan_entry DROP ingredient_id');
        $this->addSql('ALTER TABLE meal_plan_entry ALTER dish_id SET NOT NULL');
    }
}

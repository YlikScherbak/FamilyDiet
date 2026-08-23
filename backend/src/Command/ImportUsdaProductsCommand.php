<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Ідемпотентний імпорт продуктів USDA FoodData Central (SR Legacy) у довідник інгредієнтів.
 * НЕ очищає базу: upsert за fdc_id, ручні інгредієнти (fdc_id IS NULL) не чіпає.
 */
#[AsCommand(name: 'app:import-usda-products', description: 'Імпорт продуктів USDA з JSON (upsert за fdc_id)')]
class ImportUsdaProductsCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::OPTIONAL, 'Шлях до JSON', __DIR__.'/../../data/usda_products.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getArgument('file');

        $content = file_get_contents($file);
        if ($content === false) {
            $io->error('Не вдалося прочитати '.$file);

            return Command::FAILURE;
        }

        $products = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $io->progressStart(count($products));

        $sql = <<<'SQL'
            INSERT INTO ingredient (name, category, unit, kcal_per100, protein_per100, fat_per100, carbs_per100, piece_weight_grams, fdc_id)
            VALUES (:name, :category, 'g', :kcal, :protein, :fat, :carbs, NULL, :fdcId)
            ON CONFLICT (fdc_id) DO UPDATE SET
                -- Перекладені назви (name_en заповнений) не перезаписуємо
                name = CASE WHEN ingredient.name_en IS NULL THEN EXCLUDED.name ELSE ingredient.name END,
                category = EXCLUDED.category,
                kcal_per100 = EXCLUDED.kcal_per100,
                protein_per100 = EXCLUDED.protein_per100,
                fat_per100 = EXCLUDED.fat_per100,
                carbs_per100 = EXCLUDED.carbs_per100
            SQL;

        $this->connection->beginTransaction();
        try {
            foreach ($products as $product) {
                $this->connection->executeStatement($sql, [
                    'name' => mb_substr($product['name'], 0, 150),
                    'category' => $product['category'],
                    'kcal' => $product['kcal'],
                    'protein' => $product['protein'],
                    'fat' => $product['fat'],
                    'carbs' => $product['carbs'],
                    'fdcId' => $product['fdcId'],
                ]);
                $io->progressAdvance();
            }
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        $io->progressFinish();
        $total = $this->connection->fetchOne('SELECT count(*) FROM ingredient');
        $io->success(sprintf('Імпортовано/оновлено %d продуктів. Всього інгредієнтів у базі: %d.', count($products), $total));

        return Command::SUCCESS;
    }
}

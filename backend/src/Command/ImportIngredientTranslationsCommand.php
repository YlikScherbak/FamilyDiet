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
 * Завантаження українських назв інгредієнтів із CSV (fdc_id;назва).
 * Оригінальна англійська назва зберігається у name_en. Ідемпотентно.
 */
#[AsCommand(name: 'app:import-ingredient-translations', description: 'Українські назви інгредієнтів із CSV (fdc_id;назва)')]
class ImportIngredientTranslationsCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::OPTIONAL, 'Шлях до CSV', __DIR__.'/../../data/usda_names_uk.csv');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getArgument('file');

        $handle = fopen($file, 'r');
        if ($handle === false) {
            $io->error('Не вдалося прочитати '.$file);

            return Command::FAILURE;
        }

        $translations = [];
        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            if (count($row) < 2 || !is_numeric($row[0]) || trim($row[1]) === '') {
                continue;
            }
            $translations[(int) $row[0]] = mb_substr(trim($row[1]), 0, 300);
        }
        fclose($handle);

        if ($translations === []) {
            $io->error('У файлі немає валідних рядків формату fdc_id;назва');

            return Command::FAILURE;
        }

        /** @var array<int, array{id: int, name: string, fdc_id: int}> $existing */
        $existing = $this->connection->fetchAllAssociative(
            'SELECT id, name, name_en, fdc_id FROM ingredient WHERE fdc_id IS NOT NULL'
        );
        $byFdc = array_column($existing, null, 'fdc_id');

        // Назви, які залишаться зайнятими рядками поза цим імпортом.
        $taken = $this->connection->fetchFirstColumn('SELECT name FROM ingredient WHERE fdc_id IS NULL');
        $taken = array_fill_keys($taken, true);

        $updated = 0;
        $skipped = 0;
        $renamedDuplicates = 0;

        $this->connection->beginTransaction();
        try {
            foreach ($translations as $fdcId => $name) {
                $row = $byFdc[$fdcId] ?? null;
                if ($row === null) {
                    ++$skipped;
                    continue;
                }

                // Унікальність назв: дублікати отримують суфікс (2), (3)...
                $unique = $name;
                $n = 1;
                while (isset($taken[$unique])) {
                    $unique = $name.' ('.++$n.')';
                    ++$renamedDuplicates;
                }
                $taken[$unique] = true;

                $this->connection->executeStatement(
                    'UPDATE ingredient SET name_en = COALESCE(name_en, name), name = :name WHERE id = :id',
                    ['name' => $unique, 'id' => $row['id']]
                );
                ++$updated;
            }
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        $io->success(sprintf(
            'Перекладено %d назв (пропущено %d без відповідника, %d дублікатів отримали суфікс).',
            $updated,
            $skipped,
            $renamedDuplicates
        ));

        return Command::SUCCESS;
    }
}

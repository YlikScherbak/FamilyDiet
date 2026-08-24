<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\HealthEvent;
use App\Entity\MealPlanEntry;
use App\Enum\MealType;
use App\Repository\DishRepository;
use App\Repository\FamilyMemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Демо-наповнення для швидкого огляду застосунку: меню обох на поточний тиждень
 * і вісім тижнів історії ваги. Перезаписує поточний тиждень меню і ВСЮ вагу —
 * призначено для щойно засіяної бази, не для робочих даних.
 */
#[AsCommand(name: 'app:seed-demo', description: 'Демо-дані: меню на поточний тиждень + історія ваги (перезаписує!)')]
class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FamilyMemberRepository $members,
        private readonly DishRepository $dishes,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $members = $this->members->findBy([], ['id' => 'ASC']);
        if ($members === []) {
            $io->error('Немає членів сім\'ї — спершу doctrine:fixtures:load');

            return Command::FAILURE;
        }

        $byCategory = [];
        foreach (MealType::cases() as $type) {
            $byCategory[$type->value] = $this->dishes->findBy(['category' => $type], ['code' => 'ASC']);
        }

        $monday = new \DateTimeImmutable('monday this week');
        $sunday = $monday->modify('+6 days');

        $planned = 0;
        $weighed = 0;

        $this->em->wrapInTransaction(function () use ($members, $byCategory, $monday, $sunday, &$planned, &$weighed): void {
            $this->em->createQuery(
                'DELETE FROM App\Entity\MealPlanEntry e WHERE e.date >= :from AND e.date <= :to'
            )->execute(['from' => $monday, 'to' => $sunday]);
            $this->em->createQuery(
                "DELETE FROM App\Entity\HealthEvent h WHERE h.type = 'weight'"
            )->execute();

            // Меню: кожному — свій набір страв на день, зсув по днях дає різноманіття без random
            foreach (range(0, 6) as $day) {
                $date = $monday->modify(sprintf('+%d days', $day));
                foreach ($members as $mi => $member) {
                    foreach (MealType::cases() as $slot) {
                        $list = $byCategory[$slot->value];
                        if ($list === []) {
                            continue;
                        }
                        $dish = $list[($day * 2 + $mi * 3) % count($list)];
                        $this->em->persist(
                            (new MealPlanEntry())
                                ->setDate($date)
                                ->setFamilyMember($member)
                                ->setSlot($slot)
                                ->setDish($dish)
                        );
                        ++$planned;
                    }
                }
            }

            // Вага: 8 тижнів історії подіями журналу здоров'я, легкий тренд униз, детермінований «шум»
            foreach ($members as $mi => $member) {
                $base = 92.0 - $mi * 18.0;
                foreach (range(0, 7) as $week) {
                    foreach ([0, 2, 4, 6] as $offset) {
                        $date = $monday->modify(sprintf('-%d days', (7 - $week) * 7 - $offset));
                        if ($date > $sunday) {
                            continue;
                        }
                        $wobble = (($week * 7 + $offset + $mi * 3) % 5 - 2) / 10;
                        $this->em->persist(
                            (new HealthEvent())
                                ->setFamilyMember($member)
                                ->setDate($date)
                                ->setType('weight')
                                ->setPayload(['kg' => round($base - $week * 0.3 + $wobble, 1)])
                        );
                        ++$weighed;
                    }
                }
            }
        });

        $io->success(sprintf(
            'Меню на тиждень %s — %s: %d записів; ваги: %d записів.',
            $monday->format('Y-m-d'),
            $sunday->format('Y-m-d'),
            $planned,
            $weighed
        ));

        return Command::SUCCESS;
    }
}

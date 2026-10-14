<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Kinescope\KinescopeClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:kinescope:speak-owner',
    description: 'List workspace users for KINESCOPE_SPEAK_OWNER_ID (Speak room owner)',
)]
final class KinescopeSpeakOwnerCommand extends Command
{
    public function __construct(
        private readonly KinescopeClient $kinescope,
        #[Autowire('%env(default::KINESCOPE_SPEAK_OWNER_ID)%')]
        private readonly string $currentOwnerId = '',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'pick',
            null,
            InputOption::VALUE_REQUIRED,
            'Print .env line for moderator index (1-based) or user_id UUID',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->kinescope->isConfigured()) {
            $io->error('KINESCOPE_API_TOKEN is empty.');

            return Command::FAILURE;
        }

        try {
            $candidates = $this->collectOwnerCandidates();
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ([] === $candidates) {
            $io->warning(
                'Не нашли user_id: модераторы пусты и нет Speak-комнат. Создай комнату вручную в кабинете Speak один раз — потом команда подхватит owner_id.',
            );

            return Command::FAILURE;
        }

        $current = trim($this->currentOwnerId);
        $rows = [];
        foreach ($candidates as $i => $row) {
            $mark = $current === $row['user_id'] ? ' ← сейчас в .env' : '';
            $rows[] = [
                (string) ($i + 1),
                $row['user_id'].$mark,
                $row['email'] !== '' ? $row['email'] : '—',
                $row['source'],
            ];
        }

        $io->section('Кандидаты в KINESCOPE_SPEAK_OWNER_ID');
        $io->table(['#', 'user_id', 'email', 'откуда'], $rows);

        if ('' !== $current) {
            $known = false;
            foreach ($candidates as $row) {
                if ($row['user_id'] === $current) {
                    $known = true;
                    break;
                }
            }
            if (!$known) {
                $io->warning(
                    'Текущий KINESCOPE_SPEAK_OWNER_ID не из этого workspace — отсюда «no such user in workspace».',
                );
            }
        }

        $pick = $input->getOption('pick');
        if (null !== $pick && '' !== trim((string) $pick)) {
            $chosen = $this->resolvePick(trim((string) $pick), $candidates);
            if (null === $chosen) {
                $io->error('Не найден пользователь для --pick='.$pick);

                return Command::FAILURE;
            }
            $io->section('Добавь в .env');
            $io->writeln('KINESCOPE_SPEAK_OWNER_ID='.$chosen);
            $io->note('Потом: php bin/console cache:clear — и снова «Создать в Kinescope» на встрече.');
        } elseif (1 === \count($candidates)) {
            $io->section('Один кандидат — в .env');
            $io->writeln('KINESCOPE_SPEAK_OWNER_ID='.$candidates[0]['user_id']);
            $io->note('Или: php bin/console app:kinescope:speak-owner --pick=1');
        } else {
            $io->note('Выбери: php bin/console app:kinescope:speak-owner --pick=1');
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<array{user_id: string, email: string, source: string}>
     */
    private function collectOwnerCandidates(): array
    {
        $byId = [];

        foreach ($this->kinescope->listModerators() as $mod) {
            $byId[$mod['user_id']] = [
                'user_id' => $mod['user_id'],
                'email' => $mod['email'],
                'source' => 'moderators'.('' !== $mod['username'] ? ' / '.$mod['username'] : ''),
            ];
        }

        foreach ($this->kinescope->listSpeakRooms(20) as $room) {
            $ownerId = $room['owner_id'];
            if ('' === $ownerId) {
                continue;
            }
            if (!isset($byId[$ownerId])) {
                $byId[$ownerId] = [
                    'user_id' => $ownerId,
                    'email' => '',
                    'source' => 'существующая комната: '.$room['name'],
                ];
            } else {
                $byId[$ownerId]['source'] .= '; комнаты Speak';
            }
        }

        return array_values($byId);
    }

    /**
     * @param list<array{user_id: string, email: string, source?: string}> $candidates
     */
    private function resolvePick(string $pick, array $candidates): ?string
    {
        if (ctype_digit($pick)) {
            $index = (int) $pick - 1;

            return $candidates[$index]['user_id'] ?? null;
        }

        foreach ($candidates as $row) {
            if ($row['user_id'] === $pick || ($row['email'] ?? '') === $pick) {
                return $row['user_id'];
            }
        }

        return null;
    }
}

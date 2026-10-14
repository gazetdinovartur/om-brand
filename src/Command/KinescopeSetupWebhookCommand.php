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
    name: 'app:kinescope:setup-webhook',
    description: 'Create or update outgoing Kinescope webhook for practice (live/speak/media)',
)]
final class KinescopeSetupWebhookCommand extends Command
{
    /** @var list<string> */
    public const DEFAULT_EVENTS = [
        'live.connected',
        'live.finished',
        'live.cancelled',
        'media.update.status',
        'speak.call.started',
        'speak.call.finished',
        'speak.record.finished',
    ];

    public function __construct(
        private readonly KinescopeClient $kinescope,
        #[Autowire('%env(default::APP_SITE_URL)%')]
        private readonly string $siteUrl = '',
        #[Autowire('%env(default::KINESCOPE_WEBHOOK_USER)%')]
        private readonly string $webhookUser = '',
        #[Autowire('%env(default::KINESCOPE_WEBHOOK_PASSWORD)%')]
        private readonly string $webhookPassword = '',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Webhook name in Kinescope', 'arturlun-practice')
            ->addOption('endpoint', null, InputOption::VALUE_REQUIRED, 'Receiver URL (default: APP_SITE_URL/webhooks/kinescope)')
            ->addOption('list', null, InputOption::VALUE_NONE, 'Only list existing webhooks')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print payload, do not call API');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->kinescope->isConfigured()) {
            $io->error('KINESCOPE_API_TOKEN is empty.');

            return Command::FAILURE;
        }

        if ($input->getOption('list')) {
            return $this->listWebhooks($io);
        }

        $name = trim((string) $input->getOption('name'));
        $endpoint = trim((string) ($input->getOption('endpoint') ?: $this->defaultEndpoint()));
        $login = trim($this->webhookUser);
        $password = trim($this->webhookPassword);
        $dryRun = (bool) $input->getOption('dry-run');

        if ('' === $endpoint || !str_starts_with($endpoint, 'https://')) {
            $io->error('Endpoint must be https://… Set APP_SITE_URL or --endpoint.');

            return Command::FAILURE;
        }
        if ('' === $login || '' === $password) {
            $io->error('KINESCOPE_WEBHOOK_USER and KINESCOPE_WEBHOOK_PASSWORD must be set in .env.');

            return Command::FAILURE;
        }

        $payload = [
            'name' => $name,
            'endpoint' => $endpoint,
            'login' => $login,
            'password' => '(hidden)',
            'events' => self::DEFAULT_EVENTS,
        ];

        $io->section('Webhook');
        $io->writeln(json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) ?: '');

        if ($dryRun) {
            $io->note('Dry-run: nothing sent.');

            return Command::SUCCESS;
        }

        try {
            $existing = $this->findByEndpoint($endpoint);
            if (null !== $existing) {
                $id = (string) ($existing['id'] ?? '');
                if ('' === $id) {
                    $io->error('Found webhook for this endpoint but id is missing.');

                    return Command::FAILURE;
                }
                if (!$io->confirm(sprintf('Webhook already exists (id=%s). Update events/credentials?', $id), true)) {
                    $io->warning('Aborted.');

                    return Command::SUCCESS;
                }
                $this->kinescope->updateWebhook($id, $name, $endpoint, $login, $password, self::DEFAULT_EVENTS);
                $io->success('Webhook updated: '.$id);
            } else {
                $created = $this->kinescope->createWebhook($name, $endpoint, $login, $password, self::DEFAULT_EVENTS);
                $id = (string) ($created['data']['id'] ?? $created['id'] ?? '');
                $io->success('Webhook created'.('' !== $id ? ': '.$id : '.'));
            }
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->note('Receiver: '.$endpoint.' (HTTP Basic = KINESCOPE_WEBHOOK_*).');

        return Command::SUCCESS;
    }

    private function listWebhooks(SymfonyStyle $io): int
    {
        try {
            $items = $this->kinescope->listWebhooks();
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ([] === $items) {
            $io->writeln('No webhooks.');

            return Command::SUCCESS;
        }

        foreach ($items as $item) {
            $events = $item['events'] ?? [];
            $eventsStr = \is_array($events) ? implode(', ', array_map('strval', $events)) : '';
            $io->writeln(sprintf(
                '- id=%s  name=%s  endpoint=%s  events=[%s]',
                (string) ($item['id'] ?? '?'),
                (string) ($item['name'] ?? ''),
                (string) ($item['endpoint'] ?? ''),
                $eventsStr,
            ));
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByEndpoint(string $endpoint): ?array
    {
        $needle = rtrim($endpoint, '/');
        foreach ($this->kinescope->listWebhooks() as $item) {
            $url = rtrim((string) ($item['endpoint'] ?? ''), '/');
            if ($url === $needle) {
                return $item;
            }
        }

        return null;
    }

    private function defaultEndpoint(): string
    {
        $base = rtrim(trim($this->siteUrl), '/');
        if ('' === $base) {
            return '';
        }

        return $base.'/webhooks/kinescope';
    }
}

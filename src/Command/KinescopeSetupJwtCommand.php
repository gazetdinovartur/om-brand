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
    name: 'app:kinescope:setup-jwt',
    description: 'Generate RSA key for Speak host JWT, save private PEM, upload public JWK to Kinescope',
)]
final class KinescopeSetupJwtCommand extends Command
{
    public function __construct(
        private readonly KinescopeClient $kinescope,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('kid', null, InputOption::VALUE_REQUIRED, 'Key id', 'key-'.date('Y-m-d'))
            ->addOption('years', null, InputOption::VALUE_REQUIRED, 'Public key validity years', '2')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not upload JWK, only print');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $kid = (string) $input->getOption('kid');
        $years = max(1, (int) $input->getOption('years'));
        $dryRun = (bool) $input->getOption('dry-run');

        $dir = $this->projectDir.'/var/kinescope';
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            $io->error('Cannot create '.$dir);

            return Command::FAILURE;
        }

        $privatePath = $dir.'/jwt_private.pem';
        if (is_file($privatePath)) {
            if (!$io->confirm(sprintf('File %s exists. Overwrite?', $privatePath), false)) {
                $io->warning('Aborted.');

                return Command::SUCCESS;
            }
        }

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);
        if (false === $key) {
            $io->error('openssl_pkey_new failed.');

            return Command::FAILURE;
        }

        $privatePem = '';
        if (!openssl_pkey_export($key, $privatePem)) {
            $io->error('openssl_pkey_export failed.');

            return Command::FAILURE;
        }

        $details = openssl_pkey_get_details($key);
        if (!\is_array($details) || !isset($details['rsa']['n'], $details['rsa']['e'])) {
            $io->error('Cannot read RSA public components.');

            return Command::FAILURE;
        }

        if (false === file_put_contents($privatePath, $privatePem)) {
            $io->error('Cannot write '.$privatePath);

            return Command::FAILURE;
        }
        chmod($privatePath, 0600);

        $n = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
        $e = rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '=');
        $expiresAt = (new \DateTimeImmutable(sprintf('+%d years', $years)))->format('Y-m-d\TH:i:s\Z');

        $jwk = [
            'kty' => 'RSA',
            'e' => $e,
            'use' => 'sig',
            'kid' => $kid,
            'alg' => 'RS256',
            'n' => $n,
            'expires_at' => $expiresAt,
        ];

        $io->success('Private key saved (keep secret, do not commit): '.$privatePath);

        if ($dryRun) {
            $io->section('JWK (dry-run, not uploaded)');
            $io->writeln(json_encode($jwk, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        } else {
            if (!$this->kinescope->isConfigured()) {
                $io->error('KINESCOPE_API_TOKEN is empty — cannot upload JWK. Re-run with token or --dry-run.');

                return Command::FAILURE;
            }
            try {
                $this->kinescope->uploadJwk($jwk);
                $io->success('Public JWK uploaded to Kinescope (kid='.$kid.').');
            } catch (\Throwable $exception) {
                $io->error('Upload failed: '.$exception->getMessage());
                $io->writeln('Private key is already saved. Fix the error and upload manually or re-run.');

                return Command::FAILURE;
            }
        }

        $io->section('Add to .env');
        $io->writeln('KINESCOPE_JWT_PRIVATE_KEY_PATH='.$privatePath);
        $io->writeln('KINESCOPE_JWT_KID='.$kid);
        $io->note('After that: cache:clear. Host iframe will use ?token=JWT for ROLE_ADMIN.');

        return Command::SUCCESS;
    }
}

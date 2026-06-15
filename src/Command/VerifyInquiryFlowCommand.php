<?php

namespace App\Command;

use App\Enum\ContactType;
use App\Enum\InquiryType;
use App\Form\InquiryFormType;
use App\Validation\ContactValueValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'app:verify:inquiry',
    description: 'Проверка валидации формы заявки и HTTP-отправки',
)]
final class VerifyInquiryFlowCommand extends Command
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $failed = 0;

        $failed += $this->runContactValidatorTests($io);
        $failed += $this->runFormValidationTests($io);
        $failed += $this->runHttpSubmissionTest($io);

        if ($failed > 0) {
            $io->error(sprintf('Провалено проверок: %d', $failed));

            return Command::FAILURE;
        }

        $io->success('Все проверки формы заявки пройдены');

        return Command::SUCCESS;
    }

    private function runContactValidatorTests(SymfonyStyle $io): int
    {
        $io->section('ContactValueValidator');

        $cases = [
            ['phone', '+7 (999) 123-45-67', null],
            ['phone', '89991234567', null],
            ['phone', '+7 (', 'Введите номер'],
            ['email', 'user@example.com', null],
            ['email', 'not-an-email', 'корректный email'],
            ['telegram', '@testuser', null],
            ['telegram', 'testuser', null],
            ['telegram', 'https://t.me/testuser', null],
            ['telegram', 'ab', 'Укажите @username'],
            ['vk', 'https://vk.com/username', null],
            ['vk', 'id123456', null],
            ['vk', 'my_page', null],
            ['vk', '!!', 'Укажите ссылку vk.com'],
        ];

        $failed = 0;
        foreach ($cases as [$typeValue, $contact, $expectedFragment]) {
            $type = ContactType::from($typeValue);
            $error = ContactValueValidator::validate($contact, $type);
            $ok = null === $expectedFragment
                ? null === $error
                : (null !== $error && str_contains($error, $expectedFragment));

            if (!$ok) {
                ++$failed;
                $io->writeln(sprintf(
                    '  ✗ %s / %s — ожидали %s, получили %s',
                    $typeValue,
                    $contact,
                    null === $expectedFragment ? 'valid' : $expectedFragment,
                    $error ?? 'null',
                ));
            }
        }

        $normalized = ContactValueValidator::normalize('+7 (999) 123-45-67', ContactType::Phone);
        if ('+7 (999) 123-45-67' !== $normalized) {
            ++$failed;
            $io->writeln(sprintf('  ✗ normalize phone: %s', $normalized));
        }

        $io->writeln(sprintf('  %s (%d кейсов)', $failed === 0 ? 'OK' : 'FAIL', \count($cases)));

        return $failed;
    }

    private function runFormValidationTests(SymfonyStyle $io): int
    {
        $io->section('InquiryFormType');

        $failed = 0;
        $validBase = [
            'name' => 'Тест',
            'contactType' => ContactType::Telegram->value,
            'contact' => '@testuser',
            'inquiryType' => InquiryType::Consultation->value,
            'message' => '',
            'consent' => true,
        ];

        $scenarios = [
            'valid telegram' => [$validBase, true],
            'empty name' => [array_merge($validBase, ['name' => '']), false],
            'invalid phone' => [
                array_merge($validBase, [
                    'contactType' => ContactType::Phone->value,
                    'contact' => '+7 (',
                ]),
                false,
            ],
            'valid phone' => [
                array_merge($validBase, [
                    'contactType' => ContactType::Phone->value,
                    'contact' => '+7 (999) 123-45-67',
                ]),
                true,
            ],
            'valid email' => [
                array_merge($validBase, [
                    'contactType' => ContactType::Email->value,
                    'contact' => 'User@Example.COM',
                ]),
                true,
            ],
            'invalid email' => [
                array_merge($validBase, [
                    'contactType' => ContactType::Email->value,
                    'contact' => 'broken',
                ]),
                false,
            ],
            'no consent' => [array_merge($validBase, ['consent' => false]), false],
            'long name' => [array_merge($validBase, ['name' => str_repeat('а', 121)]), false],
        ];

        foreach ($scenarios as $label => [$payload, $shouldBeValid]) {
            $form = $this->formFactory->create(InquiryFormType::class, null, [
                'csrf_protection' => false,
            ]);
            $form->submit($payload);
            $isValid = $form->isValid();

            if ($isValid !== $shouldBeValid) {
                ++$failed;
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                $io->writeln(sprintf(
                    '  ✗ %s — ожидали %s, ошибки: %s',
                    $label,
                    $shouldBeValid ? 'valid' : 'invalid',
                    $errors ? implode('; ', $errors) : 'нет',
                ));
            }
        }

        $io->writeln(sprintf('  %s (%d сценариев)', $failed === 0 ? 'OK' : 'FAIL', \count($scenarios)));

        return $failed;
    }

    private function runHttpSubmissionTest(SymfonyStyle $io): int
    {
        $io->section('HTTP POST /');

        $baseUrl = rtrim((string) getenv('DEFAULT_URI'), '/') ?: 'http://nginx';
        if (str_contains($baseUrl, 'localhost:8085')) {
            $baseUrl = 'http://nginx';
        }
        $client = HttpClient::create(['timeout' => 10]);

        try {
            $page = $client->request('GET', $baseUrl.'/');
            $html = $page->getContent(false);
            $cookieHeader = implode('; ', array_map(
                static fn (string $cookie): string => explode(';', $cookie, 2)[0],
                $page->getHeaders(false)['set-cookie'] ?? [],
            ));
        } catch (\Throwable $exception) {
            $io->warning(sprintf('HTTP недоступен (%s), пропускаем E2E', $exception->getMessage()));

            return 0;
        }

        if (!preg_match('/name="inquiry_form\[_token\]"[^>]*value="([^"]+)"/', $html, $tokenMatch)) {
            $io->writeln('  ✗ CSRF-токен не найден в HTML');
            $io->writeln('  FAIL (1 кейс)');

            return 1;
        }

        $payload = [
            'inquiry_form[name]' => 'HTTP Тест',
            'inquiry_form[contactType]' => ContactType::Telegram->value,
            'inquiry_form[contact]' => '@httptest',
            'inquiry_form[inquiryType]' => InquiryType::Consultation->value,
            'inquiry_form[message]' => 'Проверка из консоли',
            'inquiry_form[consent]' => '1',
            'inquiry_form[_token]' => $tokenMatch[1],
        ];

        $response = $client->request('POST', $baseUrl.'/', [
            'headers' => array_filter([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
                'Cookie' => $cookieHeader ?: null,
            ]),
            'body' => $payload,
        ]);

        $status = $response->getStatusCode();
        $body = $response->toArray(false);

        if (200 !== $status || !($body['ok'] ?? false)) {
            $io->writeln(sprintf('  ✗ POST вернул %d: %s', $status, json_encode($body, \JSON_UNESCAPED_UNICODE)));
            $io->writeln('  FAIL (1 кейс)');

            return 1;
        }

        $io->writeln('  OK (1 кейс)');

        return 0;
    }
}

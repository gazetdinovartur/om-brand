<?php

namespace App\Tests\Service;

use App\Entity\ChronicleBlock;
use App\Entity\ChronicleEntry;
use App\Enum\ChronicleBlockType;
use App\Service\ChronicleVkMessageBuilder;
use App\Twig\UploadPathExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ChronicleVkMessageBuilderTest extends TestCase
{
    public function testBuildAppendsSiteFooterWithShortLink(): void
    {
        $entry = new ChronicleEntry();
        $entry->setTitle('Окна и двери');
        $entry->setSlug('okna');
        $entry->setShortHash('j0m83fj0');
        $entry->setLede('Ветерок.');

        $block = new ChronicleBlock();
        $block->setType(ChronicleBlockType::Paragraph);
        $block->setBody('Текст поста.');
        $block->setSortOrder(0);
        $entry->addBlock($block);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(
            static fn (string $name, array $params): string => '/p/'.$params['hash'],
        );

        $builder = new ChronicleVkMessageBuilder(
            new UploadPathExtension(sys_get_temp_dir()),
            $urls,
            sys_get_temp_dir(),
            'https://arturlun.ru',
        );

        $payload = $builder->build($entry);

        self::assertStringContainsString('Окна и двери', $payload['message']);
        self::assertStringContainsString('Текст поста.', $payload['message']);
        self::assertStringEndsWith(
            "*\n\nОпубликовано на сайте: https://arturlun.ru/p/j0m83fj0",
            $payload['message'],
        );
        self::assertSame('https://arturlun.ru/p/j0m83fj0', $payload['shortUrl']);
    }
}

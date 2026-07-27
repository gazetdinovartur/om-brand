<?php

namespace App\Tests\Service;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;
use App\Repository\ChronicleEntryRepository;
use App\Service\ChronicleEntryVkActions;
use App\Service\ChronicleVkCrossposter;
use App\Service\ChronicleVkMessageBuilder;
use App\Service\VkApiClient;
use App\Service\VkCredentials;
use App\Twig\UploadPathExtension;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ChronicleEntryVkActionsTest extends KernelTestCase
{
  private EntityManagerInterface $em;

    private ChronicleEntryVkActions $actions;

    private ChronicleVkCrossposter $crossposter;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $creds = $this->enabledCredentials();
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/p/testhash');

        $http = new MockHttpClient(static function (string $method, string $url): MockResponse {
            if (str_contains($url, 'wall.post')) {
                $postId = random_int(200000, 999999);

                return new MockResponse(json_encode(['response' => ['post_id' => $postId]], \JSON_THROW_ON_ERROR));
            }
            if (str_contains($url, 'wall.edit')) {
                return new MockResponse(json_encode(['response' => 1], \JSON_THROW_ON_ERROR));
            }

            self::fail('Unexpected VK HTTP call: '.$url);
        });

        $this->crossposter = new ChronicleVkCrossposter(
            $this->em,
            static::getContainer()->get(ChronicleEntryRepository::class),
            $creds,
            new VkApiClient($http, $creds),
            new ChronicleVkMessageBuilder(
                new UploadPathExtension(sys_get_temp_dir()),
                $urls,
                sys_get_temp_dir(),
                'https://arturlun.ru',
            ),
            new \Psr\Log\NullLogger(),
        );

        $this->actions = new ChronicleEntryVkActions($this->em, $this->crossposter);
    }

    public function testApplySetsCrosspostRequestedWithoutPostingWhenUnchecked(): void
    {
        $entry = $this->persistPublishedEntry();
        $entry->setVkCrosspostRequested(false);

        $this->actions->applyFromPayload($entry, ['vkCrosspostRequested' => false]);
        $this->em->refresh($entry);

        self::assertFalse($entry->isVkCrosspostRequested());
        self::assertNull($entry->getVkPostId());
    }

    public function testApplyCrosspostsWhenRequestedOnPublish(): void
    {
        $entry = $this->persistPublishedEntry();
        $entry->setVkCrosspostRequested(true);

        $this->actions->applyFromPayload($entry, ['vkCrosspostRequested' => true]);
        $this->em->refresh($entry);

        self::assertTrue($entry->isVkCrosspostRequested());
        self::assertNotNull($entry->getVkPostId());
        self::assertNull($entry->getVkCrosspostError());
    }

    public function testApplyUpdatesExistingVkPostWhenFlagSet(): void
    {
        $entry = $this->persistPublishedEntry();
        $entry->setVkCrosspostRequested(true);
        $vkPostId = random_int(200000, 999999);
        $entry->setVkPostId($vkPostId);
        $this->em->flush();

        $this->actions->applyFromPayload($entry, [
            'vkCrosspostRequested' => true,
            'vkUpdateToVk' => true,
        ]);
        $this->em->refresh($entry);

        self::assertSame($vkPostId, $entry->getVkPostId());
        self::assertNull($entry->getVkCrosspostError());
    }

    private function persistPublishedEntry(): ChronicleEntry
    {
        $entry = new ChronicleEntry();
        $entry->setTitle('VK actions '.bin2hex(random_bytes(3)));
        $entry->setSlug('vk-actions-'.bin2hex(random_bytes(4)));
        $entry->setShortHash(substr(bin2hex(random_bytes(4)), 0, 8));
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt(new \DateTimeImmutable('-1 hour'));
        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    private function enabledCredentials(): VkCredentials
    {
        $dir = sys_get_temp_dir().'/vk-actions-'.bin2hex(random_bytes(4));
        mkdir($dir.'/vk', 0700, true);
        file_put_contents($dir.'/vk/user_access_token', "token\n");

        return new VkCredentials(
            projectDir: $dir,
            userTokenEnv: 'token-from-env',
            ownerIdEnv: '-12345',
            enabledEnv: '1',
        );
    }
}

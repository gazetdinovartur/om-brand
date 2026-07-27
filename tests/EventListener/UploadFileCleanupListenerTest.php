<?php

namespace App\Tests\EventListener;

use App\Entity\CaseStudy;
use App\Entity\CaseStudyImage;
use App\Entity\ChronicleBlock;
use App\Entity\ChronicleBlockImage;
use App\Entity\ChronicleEntry;
use App\Entity\Inquiry;
use App\Entity\SiteSettings;
use App\Enum\ChronicleBlockType;
use App\Enum\ChronicleStatus;
use App\Enum\ContactType;
use App\Enum\InquiryType;
use App\Repository\SiteSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Act-observe: create disposable files + entities, remove/replace via Doctrine, assert disk cleanup.
 */
final class UploadFileCleanupListenerTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private string $publicUploads;

    private string $privateUploads;

    /** @var list<string> */
    private array $createdPublicFiles = [];

    /** @var list<string> */
    private array $createdPrivateFiles = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->publicUploads = (string) static::getContainer()->getParameter('app.uploads_directory');
        $this->privateUploads = (string) static::getContainer()->getParameter('app.private_uploads_directory');
    }

    protected function tearDown(): void
    {
        foreach ($this->createdPublicFiles as $relative) {
            $absolute = rtrim($this->publicUploads, '/').'/'.$relative;
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }
        foreach ($this->createdPrivateFiles as $relative) {
            $absolute = rtrim($this->privateUploads, '/').'/'.$relative;
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }
        parent::tearDown();
    }

    public function testRemovingCaseStudyDeletesCoverGalleryAndAudioFromDisk(): void
    {
        $token = bin2hex(random_bytes(4));
        $cover = "cleanup-cover-{$token}.webp";
        $coverThumb = "cleanup-cover-{$token}.thumb.webp";
        $gallery = "cleanup-gallery-{$token}.webp";
        $galleryThumb = "cleanup-gallery-{$token}.thumb.webp";
        $audio = "cleanup-audio-{$token}.mp3";

        $this->placePublic("cases/{$cover}");
        $this->placePublic("cases/{$coverThumb}");
        $this->placePublic("cases/gallery/{$gallery}");
        $this->placePublic("cases/gallery/{$galleryThumb}");
        $this->placePublic("cases/audio/{$audio}");

        $case = new CaseStudy();
        $case->setTitle("Cleanup case {$token}");
        $case->setSlug("cleanup-case-{$token}");
        $case->setCoverImagePath($cover);
        $case->setAudioPath($audio);
        $case->setIsPublished(false);

        $image = new CaseStudyImage();
        $image->setImagePath($gallery);
        $image->setSortOrder(0);
        $case->addGalleryImage($image);

        $this->em->persist($case);
        $this->em->flush();
        $caseId = $case->getId();
        self::assertNotNull($caseId);

        self::assertFileExists($this->publicPath("cases/{$cover}"));
        self::assertFileExists($this->publicPath("cases/gallery/{$gallery}"));
        self::assertFileExists($this->publicPath("cases/audio/{$audio}"));

        $this->em->remove($case);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->em->find(CaseStudy::class, $caseId));
        self::assertFileDoesNotExist($this->publicPath("cases/{$cover}"));
        self::assertFileDoesNotExist($this->publicPath("cases/{$coverThumb}"));
        self::assertFileDoesNotExist($this->publicPath("cases/gallery/{$gallery}"));
        self::assertFileDoesNotExist($this->publicPath("cases/gallery/{$galleryThumb}"));
        self::assertFileDoesNotExist($this->publicPath("cases/audio/{$audio}"));
    }

    public function testReplacingCaseCoverDeletesPreviousFile(): void
    {
        $token = bin2hex(random_bytes(4));
        $old = "cleanup-old-cover-{$token}.webp";
        $new = "cleanup-new-cover-{$token}.webp";
        $this->placePublic("cases/{$old}");
        $this->placePublic("cases/{$new}");

        $case = new CaseStudy();
        $case->setTitle("Cleanup replace {$token}");
        $case->setSlug("cleanup-replace-{$token}");
        $case->setCoverImagePath($old);
        $this->em->persist($case);
        $this->em->flush();

        $case->setCoverImagePath($new);
        $this->em->flush();

        self::assertFileDoesNotExist($this->publicPath("cases/{$old}"));
        self::assertFileExists($this->publicPath("cases/{$new}"));

        $this->em->remove($case);
        $this->em->flush();
        self::assertFileDoesNotExist($this->publicPath("cases/{$new}"));
    }

    public function testRemovingChronicleEntryDeletesCoverInlineGalleryAndAudio(): void
    {
        $token = bin2hex(random_bytes(4));
        $cover = "cleanup-ch-cover-{$token}.webp";
        $inline = "cleanup-ch-inline-{$token}.webp";
        $gallery = "cleanup-ch-gallery-{$token}.webp";
        $audioRel = "chronicle/audio/cleanup-ch-audio-{$token}.mp3";

        $this->placePublic("chronicle/covers/{$cover}");
        $this->placePublic("chronicle/covers/".str_replace('.webp', '.thumb.webp', $cover));
        $this->placePublic("chronicle/inline/{$inline}");
        $this->placePublic("chronicle/gallery/{$gallery}");
        $this->placePublic($audioRel);

        $entry = new ChronicleEntry();
        $entry->setTitle("Cleanup chronicle {$token}");
        $entry->setSlug("cleanup-chronicle-{$token}");
        $entry->setShortHash(substr($token, 0, 8));
        $entry->setStatus(ChronicleStatus::Draft);
        $entry->setCoverImagePath($cover);

        $imageBlock = new ChronicleBlock();
        $imageBlock->setType(ChronicleBlockType::Image);
        $imageBlock->setSortOrder(0);
        $imageBlock->setImagePath($inline);
        $entry->addBlock($imageBlock);

        $galleryBlock = new ChronicleBlock();
        $galleryBlock->setType(ChronicleBlockType::Gallery);
        $galleryBlock->setSortOrder(10);
        $galleryImage = new ChronicleBlockImage();
        $galleryImage->setImagePath($gallery);
        $galleryImage->setSortOrder(0);
        $galleryBlock->addImage($galleryImage);
        $entry->addBlock($galleryBlock);

        $audioBlock = new ChronicleBlock();
        $audioBlock->setType(ChronicleBlockType::Audio);
        $audioBlock->setSortOrder(20);
        $audioBlock->setVideoUrl($audioRel);
        $entry->addBlock($audioBlock);

        $this->em->persist($entry);
        $this->em->flush();
        $entryId = $entry->getId();

        $this->em->remove($entry);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->em->find(ChronicleEntry::class, $entryId));
        self::assertFileDoesNotExist($this->publicPath("chronicle/covers/{$cover}"));
        self::assertFileDoesNotExist($this->publicPath('chronicle/covers/'.str_replace('.webp', '.thumb.webp', $cover)));
        self::assertFileDoesNotExist($this->publicPath("chronicle/inline/{$inline}"));
        self::assertFileDoesNotExist($this->publicPath("chronicle/gallery/{$gallery}"));
        self::assertFileDoesNotExist($this->publicPath($audioRel));
    }

    public function testRemovingInquiryDeletesPrivateAttachment(): void
    {
        $token = bin2hex(random_bytes(4));
        $relative = "inquiries/cleanup-inquiry-{$token}.pdf";
        $this->placePrivate($relative);

        $inquiry = new Inquiry();
        $inquiry->setName('Cleanup');
        $inquiry->setContact('cleanup@localhost');
        $inquiry->setContactType(ContactType::Email);
        $inquiry->setInquiryType(InquiryType::Unsure);
        $inquiry->setMessage('cleanup attachment test');
        $inquiry->setAttachmentPath($relative);
        $inquiry->setAttachmentOriginalName('cleanup.pdf');
        $inquiry->setAttachmentMimeType('application/pdf');

        $this->em->persist($inquiry);
        $this->em->flush();
        $id = $inquiry->getId();

        self::assertFileExists($this->privatePath($relative));

        $this->em->remove($inquiry);
        $this->em->flush();

        self::assertNull($this->em->find(Inquiry::class, $id));
        self::assertFileDoesNotExist($this->privatePath($relative));
    }

    public function testReplacingAvatarDeletesPreviousVariants(): void
    {
        /** @var SiteSettingsRepository $repo */
        $repo = $this->em->getRepository(SiteSettings::class);
        $settings = $repo->findOneBy([]) ?? $repo->getSettings();
        if (null === $settings->getId()) {
            $this->em->persist($settings);
        }
        $originalAvatar = $settings->getAvatarPath();

        $token = bin2hex(random_bytes(4));
        $old = "cleanup-avatar-old-{$token}.webp";
        $oldThumb = "cleanup-avatar-old-{$token}.thumb.webp";
        $new = "cleanup-avatar-new-{$token}.webp";
        $this->placePublic("avatars/{$old}");
        $this->placePublic("avatars/{$oldThumb}");
        $this->placePublic("avatars/{$new}");

        $settings->setAvatarPath($old);
        $this->em->flush();

        $settings->setAvatarPath($new);
        $this->em->flush();

        self::assertFileDoesNotExist($this->publicPath("avatars/{$old}"));
        self::assertFileDoesNotExist($this->publicPath("avatars/{$oldThumb}"));
        self::assertFileExists($this->publicPath("avatars/{$new}"));

        // Restore production avatar; replacing again should delete the disposable new file.
        $settings->setAvatarPath($originalAvatar);
        $this->em->flush();
        self::assertFileDoesNotExist($this->publicPath("avatars/{$new}"));
    }

    private function placePublic(string $relative): void
    {
        $absolute = $this->publicPath($relative);
        $dir = \dirname($absolute);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($absolute, 'cleanup-test');
        $this->createdPublicFiles[] = $relative;
    }

    private function placePrivate(string $relative): void
    {
        $absolute = $this->privatePath($relative);
        $dir = \dirname($absolute);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($absolute, 'cleanup-test');
        $this->createdPrivateFiles[] = $relative;
    }

    private function publicPath(string $relative): string
    {
        return rtrim($this->publicUploads, '/').'/'.$relative;
    }

    private function privatePath(string $relative): string
    {
        return rtrim($this->privateUploads, '/').'/'.$relative;
    }
}

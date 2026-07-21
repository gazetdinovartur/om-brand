<?php

namespace App\Service;

use App\Entity\CaseStudy;
use App\Entity\ChronicleEntry;
use App\Entity\ContentLike;
use App\Enum\ContentLikeTarget;
use App\Repository\CaseStudyRepository;
use App\Repository\ChronicleEntryRepository;
use App\Repository\ContentLikeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ContentLikeService
{
    public const COOKIE_NAME = 'om_visitor';

    private const COOKIE_TTL_DAYS = 400;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ContentLikeRepository $likes,
        private readonly ChronicleEntryRepository $entries,
        private readonly CaseStudyRepository $cases,
        private readonly ImportedLikeCountParser $importedLikes,
    ) {
    }

    /**
     * @return array{liked: bool, count: int, targetType: string, targetId: int}
     */
    public function status(ContentLikeTarget $type, int $targetId, Request $request): array
    {
        $subject = $this->requireSubject($type, $targetId);
        $token = $this->readVisitorToken($request);
        $liked = null !== $token && null !== $this->likes->findOneForVisitor($type, $targetId, $token);

        return [
            'liked' => $liked,
            'count' => $subject->getLikeCount(),
            'targetType' => $type->value,
            'targetId' => $targetId,
        ];
    }

    /**
     * @return array{liked: bool, count: int, targetType: string, targetId: int}
     */
    public function toggle(ContentLikeTarget $type, int $targetId, Request $request, Response $response): array
    {
        $subject = $this->requireSubject($type, $targetId);
        $token = $this->ensureVisitorToken($request, $response);
        $existing = $this->likes->findOneForVisitor($type, $targetId, $token);

        if (null !== $existing) {
            $this->em->remove($existing);
            $subject->setLikeCount($subject->getLikeCount() - 1);
            $liked = false;
        } else {
            $this->em->persist(new ContentLike($type, $targetId, $token));
            $subject->setLikeCount($subject->getLikeCount() + 1);
            $liked = true;
        }

        $this->em->flush();

        return [
            'liked' => $liked,
            'count' => $subject->getLikeCount(),
            'targetType' => $type->value,
            'targetId' => $targetId,
        ];
    }

    /**
     * Apply imported ❤ counts from meta callout blocks onto like_count.
     * Preserves visitor likes: total = max(imported, current_visitor_floor) where
     * visitor_floor = current like_count if we can't separate; safer formula:
     * new = imported + visitor_likes_count (visitor rows only).
     *
     * @return int Number of entries updated
     */
    public function seedImportedChronicleLikes(bool $dryRun = false): int
    {
        $updated = 0;
        $entries = $this->entries->createQueryBuilder('e')
            ->leftJoin('e.blocks', 'b')->addSelect('b')
            ->getQuery()
            ->getResult();

        foreach ($entries as $entry) {
            if (!$entry instanceof ChronicleEntry) {
                continue;
            }

            $imported = 0;
            foreach ($entry->getBlocks() as $block) {
                if ('meta' !== $block->getCalloutStyle()) {
                    continue;
                }
                $parsed = $this->importedLikes->parse($block->getBody());
                if (null !== $parsed) {
                    $imported = max($imported, $parsed);
                }
            }

            $visitorLikes = $this->likes->countForTarget(ContentLikeTarget::Chronicle, (int) $entry->getId());
            $next = $imported + $visitorLikes;
            if ($next === $entry->getLikeCount()) {
                continue;
            }

            ++$updated;
            if (!$dryRun) {
                $entry->setLikeCount($next);
            }
        }

        if (!$dryRun && $updated > 0) {
            $this->em->flush();
        }

        return $updated;
    }

    public function applyImportedCountFromBlocks(ChronicleEntry $entry): void
    {
        $imported = 0;
        foreach ($entry->getBlocks() as $block) {
            if ('meta' !== $block->getCalloutStyle()) {
                continue;
            }
            $parsed = $this->importedLikes->parse($block->getBody());
            if (null !== $parsed) {
                $imported = max($imported, $parsed);
            }
        }

        $id = $entry->getId();
        $visitorLikes = null !== $id
            ? $this->likes->countForTarget(ContentLikeTarget::Chronicle, $id)
            : 0;

        $entry->setLikeCount($imported + $visitorLikes);
    }

    public function readVisitorToken(Request $request): ?string
    {
        $raw = (string) $request->cookies->get(self::COOKIE_NAME, '');
        if (1 !== preg_match('/^[a-f0-9]{32,64}$/', $raw)) {
            return null;
        }

        return $raw;
    }

    public function ensureVisitorToken(Request $request, Response $response): string
    {
        $existing = $this->readVisitorToken($request);
        if (null !== $existing) {
            return $existing;
        }

        $token = bin2hex(random_bytes(16));
        $response->headers->setCookie(Cookie::create(self::COOKIE_NAME)
            ->withValue($token)
            ->withExpires(new \DateTimeImmutable(sprintf('+%d days', self::COOKIE_TTL_DAYS)))
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX));

        return $token;
    }

    private function requireSubject(ContentLikeTarget $type, int $targetId): ChronicleEntry|CaseStudy
    {
        $subject = match ($type) {
            ContentLikeTarget::Chronicle => $this->entries->find($targetId),
            ContentLikeTarget::Case => $this->cases->find($targetId),
        };

        if ($subject instanceof ChronicleEntry) {
            if (!$subject->isPublic()) {
                throw new \InvalidArgumentException('Контент не найден.');
            }

            return $subject;
        }

        if ($subject instanceof CaseStudy) {
            if (!$subject->isPublished()) {
                throw new \InvalidArgumentException('Контент не найден.');
            }

            return $subject;
        }

        throw new \InvalidArgumentException('Контент не найден.');
    }
}

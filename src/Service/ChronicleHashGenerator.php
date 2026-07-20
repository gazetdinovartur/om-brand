<?php

namespace App\Service;

use App\Entity\ChronicleBlock;
use App\Repository\ChronicleEntryRepository;

final class ChronicleHashGenerator
{
    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyz';

    public function __construct(
        private readonly ChronicleEntryRepository $entries,
    ) {
    }

    public function generateUnique(?int $excludeId = null): string
    {
        for ($attempt = 0; $attempt < 50; ++$attempt) {
            $hash = $this->randomHash();
            if (!$this->entries->isShortHashTaken($hash, $excludeId)) {
                return $hash;
            }
        }

        return substr(bin2hex(random_bytes(4)), 0, 8);
    }

    private function randomHash(): string
    {
        $bytes = random_bytes(6);
        $result = '';
        for ($i = 0; $i < 8; ++$i) {
            $result .= self::ALPHABET[ord($bytes[$i % 6]) % \strlen(self::ALPHABET)];
        }

        return $result;
    }
}

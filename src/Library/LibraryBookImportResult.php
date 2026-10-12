<?php

declare(strict_types=1);

namespace App\Library;

use App\Entity\LibraryBook;

final readonly class LibraryBookImportResult
{
    public const OUTCOME_CREATED = 'created';
    public const OUTCOME_UPDATED = 'updated';
    public const OUTCOME_UNCHANGED = 'unchanged';

    public function __construct(
        public LibraryBook $book,
        public string $outcome,
        public LibraryBookImportPreview $preview,
    ) {
    }

    public function message(): string
    {
        return match ($this->outcome) {
            self::OUTCOME_CREATED => 'Книга импортирована.',
            self::OUTCOME_UPDATED => 'Книга обновлена.',
            self::OUTCOME_UNCHANGED => 'Источник уже импортирован, изменений нет.',
            default => 'Импорт завершён.',
        };
    }
}

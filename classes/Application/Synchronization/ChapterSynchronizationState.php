<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization;

final class ChapterSynchronizationState
{
    public function __construct(
        private object $chapter,
        private string $imprintId,
        private ?string $locale
    ) {
    }

    public function getChapter(): object
    {
        return $this->chapter;
    }

    public function getImprintId(): string
    {
        return $this->imprintId;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }
}

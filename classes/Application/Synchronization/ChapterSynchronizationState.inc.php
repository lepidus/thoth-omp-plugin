<?php

final class ChapterSynchronizationState
{
    private object $chapter;
    private string $imprintId;
    private ?string $locale;

    public function __construct(object $chapter, string $imprintId, ?string $locale)
    {
        $this->chapter = $chapter;
        $this->imprintId = $imprintId;
        $this->locale = $locale;
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

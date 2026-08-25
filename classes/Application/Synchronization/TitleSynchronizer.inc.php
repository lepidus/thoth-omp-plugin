<?php


final class TitleSynchronizer implements DomainSynchronizer
{
    private const MUTABLE_FIELDS = ['localeCode', 'fullTitle', 'title', 'subtitle', 'canonical'];
    private TitleMetadataGateway $gateway;
    private TitleMetadataMapper $mapper;

    public function __construct(TitleMetadataGateway $gateway, TitleMetadataMapper $mapper)
    {
        $this->gateway = $gateway;
        $this->mapper = $mapper;
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $remoteByLocale = $this->indexRemoteTitles($this->gateway->snapshot($workId));
        $desiredByLocale = $this->indexDesiredTitles($this->mapper->fromPublication($desiredState, $workId));
        $canonicalTitle = null;
        $existingCanonicalTitle = null;

        foreach ($desiredByLocale as $locale => $desiredTitle) {
            if (!$desiredTitle['canonical']) {
                continue;
            }

            $canonicalTitle = $desiredTitle;
            $existingCanonicalTitle = $remoteByLocale[$locale] ?? null;
            unset($desiredByLocale[$locale], $remoteByLocale[$locale]);
            break;
        }

        foreach ($desiredByLocale as $locale => $desiredTitle) {
            $this->save($workId, $desiredTitle, $remoteByLocale[$locale] ?? null);
            unset($remoteByLocale[$locale]);
        }

        foreach ($remoteByLocale as $remoteTitle) {
            $this->gateway->delete($remoteTitle['titleId']);
        }

        if ($canonicalTitle !== null) {
            $this->save($workId, $canonicalTitle, $existingCanonicalTitle);
        }

        return new SynchronizationResult();
    }

    private function save(WorkId $workId, array $desiredTitle, ?array $remoteTitle): void
    {
        if ($remoteTitle === null) {
            $this->gateway->create($workId, $desiredTitle);
            return;
        }

        if ($this->hasChanges($remoteTitle, $desiredTitle)) {
            $this->gateway->update($workId, $remoteTitle['titleId'], $desiredTitle);
        }
    }

    private function hasChanges(array $remoteTitle, array $desiredTitle): bool
    {
        foreach (self::MUTABLE_FIELDS as $field) {
            if (($remoteTitle[$field] ?? null) !== ($desiredTitle[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function indexRemoteTitles(array $titles): array
    {
        $indexed = [];
        foreach ($titles as $title) {
            $locale = $this->localeKey($title['localeCode'] ?? null);
            if ($locale === '' || !isset($title['titleId'])) {
                throw new InvalidRemoteMetadata('synchronizeTitles', 'incompleteRemoteTitle');
            }
            if (isset($indexed[$locale])) {
                throw new InvalidRemoteMetadata(
                    'synchronizeTitles',
                    'ambiguousRemoteTitle',
                    ['localeCode' => $locale]
                );
            }

            $title['localeCode'] = $locale;
            $indexed[$locale] = $title;
        }

        return $indexed;
    }

    private function indexDesiredTitles(array $titles): array
    {
        $indexed = [];
        foreach ($titles as $title) {
            $locale = $this->localeKey($title['localeCode'] ?? null);
            if ($locale === '') {
                continue;
            }

            $title['localeCode'] = $locale;
            $indexed[$locale] = $title;
        }

        return $indexed;
    }

    private function localeKey(?string $localeCode): string
    {
        return $localeCode === null ? '' : strtoupper(str_replace('-', '_', $localeCode));
    }
}

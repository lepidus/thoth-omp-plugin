<?php


final class AbstractSynchronizer implements DomainSynchronizer
{
    private const MUTABLE_FIELDS = ['localeCode', 'content', 'abstractType', 'canonical'];
    private AbstractMetadataGateway $gateway;
    private AbstractMetadataMapper $mapper;

    public function __construct(AbstractMetadataGateway $gateway, AbstractMetadataMapper $mapper)
    {
        $this->gateway = $gateway;
        $this->mapper = $mapper;
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $remoteByLocale = $this->indexRemoteAbstracts($this->gateway->snapshot($workId));
        $desiredByLocale = $this->indexDesiredAbstracts($this->mapper->fromPublication($desiredState, $workId));
        $canonicalAbstract = null;
        $existingCanonicalAbstract = null;

        foreach ($desiredByLocale as $locale => $desiredAbstract) {
            if (!$desiredAbstract['canonical']) {
                continue;
            }

            $canonicalAbstract = $desiredAbstract;
            $existingCanonicalAbstract = $remoteByLocale[$locale] ?? null;
            unset($desiredByLocale[$locale], $remoteByLocale[$locale]);
            break;
        }

        foreach ($desiredByLocale as $locale => $desiredAbstract) {
            $this->save($workId, $desiredAbstract, $remoteByLocale[$locale] ?? null);
            unset($remoteByLocale[$locale]);
        }

        foreach ($remoteByLocale as $remoteAbstract) {
            $this->gateway->delete($remoteAbstract['abstractId']);
        }

        if ($canonicalAbstract !== null) {
            $this->save($workId, $canonicalAbstract, $existingCanonicalAbstract);
        }

        return new SynchronizationResult();
    }

    private function save(WorkId $workId, array $desiredAbstract, ?array $remoteAbstract): void
    {
        if ($remoteAbstract === null) {
            $this->gateway->create($workId, $desiredAbstract);
            return;
        }

        if ($this->hasChanges($remoteAbstract, $desiredAbstract)) {
            $this->gateway->update($workId, $remoteAbstract['abstractId'], $desiredAbstract);
        }
    }

    private function hasChanges(array $remoteAbstract, array $desiredAbstract): bool
    {
        foreach (self::MUTABLE_FIELDS as $field) {
            if (($remoteAbstract[$field] ?? null) !== ($desiredAbstract[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function indexRemoteAbstracts(array $abstracts): array
    {
        $indexed = [];
        foreach ($abstracts as $abstract) {
            $locale = $this->localeKey($abstract['localeCode'] ?? null);
            if ($locale === '' || !isset($abstract['abstractId'])) {
                throw new InvalidRemoteMetadata('synchronizeAbstracts', 'incompleteRemoteAbstract');
            }
            if (isset($indexed[$locale])) {
                throw new InvalidRemoteMetadata(
                    'synchronizeAbstracts',
                    'ambiguousRemoteAbstract',
                    ['localeCode' => $locale]
                );
            }

            $abstract['localeCode'] = $locale;
            $indexed[$locale] = $abstract;
        }

        return $indexed;
    }

    private function indexDesiredAbstracts(array $abstracts): array
    {
        $indexed = [];
        foreach ($abstracts as $abstract) {
            $locale = $this->localeKey($abstract['localeCode'] ?? null);
            if ($locale === '') {
                continue;
            }

            $abstract['localeCode'] = $locale;
            $indexed[$locale] = $abstract;
        }

        return $indexed;
    }

    private function localeKey(?string $localeCode): string
    {
        return $localeCode === null ? '' : strtoupper(str_replace('-', '_', $localeCode));
    }
}

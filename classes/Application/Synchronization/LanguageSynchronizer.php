<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization;

use APP\plugins\generic\thoth\classes\Application\Exception\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Contracts\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Contracts\LanguageMetadataGateway;
use APP\plugins\generic\thoth\classes\Contracts\LanguageMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;

final class LanguageSynchronizer implements DomainSynchronizer
{
    private const ORIGINAL = 'ORIGINAL';

    public function __construct(
        private LanguageMetadataGateway $gateway,
        private LanguageMetadataMapper $mapper
    ) {
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $originalLanguages = array_values(array_filter(
            $this->gateway->snapshot($workId),
            fn (array $language): bool => ($language['languageRelation'] ?? null) === self::ORIGINAL
        ));
        if (count($originalLanguages) > 1) {
            throw new InvalidRemoteMetadata('synchronizeLanguages', 'ambiguousRemoteOriginalLanguage');
        }

        $remoteLanguage = $originalLanguages[0] ?? null;
        if ($remoteLanguage !== null && !$this->isComplete($remoteLanguage)) {
            throw new InvalidRemoteMetadata('synchronizeLanguages', 'incompleteRemoteOriginalLanguage');
        }

        $desiredLanguage = $this->mapper->fromPublication($desiredState, $workId);
        $desiredLanguage['languageCode'] = $this->normalizeCode($desiredLanguage['languageCode'] ?? null);

        if ($remoteLanguage === null) {
            $this->gateway->create($workId, $desiredLanguage);
        } elseif ($this->normalizeCode($remoteLanguage['languageCode']) !== $desiredLanguage['languageCode']) {
            $this->gateway->update($workId, $remoteLanguage['languageId'], $desiredLanguage);
        }

        return new SynchronizationResult();
    }

    private function isComplete(array $language): bool
    {
        return is_string($language['languageId'] ?? null)
            && $language['languageId'] !== ''
            && $this->normalizeCode($language['languageCode'] ?? null) !== '';
    }

    private function normalizeCode(?string $languageCode): string
    {
        return strtoupper(trim((string) $languageCode));
    }
}

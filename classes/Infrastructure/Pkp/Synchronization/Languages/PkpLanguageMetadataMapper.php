<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Languages;

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\LanguageMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use PKP\i18n\LocaleConversion;

final class PkpLanguageMetadataMapper implements LanguageMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array
    {
        return [
            'languageCode' => strtoupper(LocaleConversion::get3LetterIsoFromLocale($publication->getData('locale'))),
            'languageRelation' => 'ORIGINAL',
        ];
    }
}

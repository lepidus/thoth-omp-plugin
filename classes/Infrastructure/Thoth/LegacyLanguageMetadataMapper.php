<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Contracts\LanguageMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use PKP\i18n\LocaleConversion;

final class LegacyLanguageMetadataMapper implements LanguageMetadataMapper
{
    private const ORIGINAL = 'ORIGINAL';

    public function fromPublication(object $publication, WorkId $workId): array
    {
        return [
            'languageCode' => strtoupper(LocaleConversion::get3LetterIsoFromLocale($publication->getData('locale'))),
            'languageRelation' => self::ORIGINAL,
        ];
    }
}

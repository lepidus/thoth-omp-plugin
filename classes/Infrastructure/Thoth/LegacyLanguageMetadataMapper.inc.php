<?php

import('plugins.generic.thoth.classes.Contracts.LanguageMetadataMapper');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class LegacyLanguageMetadataMapper implements LanguageMetadataMapper
{
    private const ORIGINAL = 'ORIGINAL';

    public function fromPublication(object $publication, WorkId $workId): array
    {
        $locale = $publication->getData('locale');
        if (!is_string($locale) || strlen($locale) < 5) {
            $locale = AppLocale::getLocale();
        }

        return [
            'languageCode' => strtoupper(AppLocale::get3LetterIsoFromLocale($locale)),
            'languageRelation' => self::ORIGINAL,
        ];
    }
}

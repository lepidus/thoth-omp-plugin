<?php

final class PkpLanguageMetadataMapper implements LanguageMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array
    {
        return [
            'languageCode' => strtoupper(AppLocale::get3LetterIsoFromLocale($publication->getData('locale'))),
            'languageRelation' => 'ORIGINAL',
        ];
    }
}

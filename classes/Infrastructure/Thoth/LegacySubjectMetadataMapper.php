<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Contracts\SubjectMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use ThothApi\GraphQL\Enums\SubjectType;

final class LegacySubjectMetadataMapper implements SubjectMetadataMapper
{
    public function __construct(private object $classifier)
    {
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        $locale = $publication->getData('locale');
        $publicationSubjects = $publication->getData('subjects') ?? [];
        $keywords = $publication->getData('keywords') ?? [];
        $subjects = [];
        $seenSubjects = [];

        foreach ($publicationSubjects[$locale] ?? [] as $subject) {
            $this->appendSubject($subjects, $seenSubjects, $this->classifier->classify($subject));
        }
        foreach ($keywords[$locale] ?? [] as $keyword) {
            $this->appendSubject($subjects, $seenSubjects, [
                'subjectType' => SubjectType::KEYWORD,
                'subjectCode' => trim((string) ($keyword['name'] ?? $keyword)),
            ]);
        }

        return $subjects;
    }

    private function appendSubject(array &$subjects, array &$seenSubjects, array $subject): void
    {
        $subject['subjectType'] = strtoupper(trim((string) ($subject['subjectType'] ?? '')));
        $subject['subjectCode'] = trim((string) ($subject['subjectCode'] ?? ''));
        if ($subject['subjectType'] === '' || $subject['subjectCode'] === '') {
            return;
        }

        $key = $subject['subjectType'] . "\0" . $subject['subjectCode'];
        if (isset($seenSubjects[$key])) {
            return;
        }

        $seenSubjects[$key] = true;
        $subject['subjectOrdinal'] = count($subjects) + 1;
        $subjects[] = $subject;
    }
}

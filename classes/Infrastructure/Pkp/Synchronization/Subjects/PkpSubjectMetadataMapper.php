<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Subjects;

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\SubjectMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use ThothApi\GraphQL\Enums\SubjectType;

final class PkpSubjectMetadataMapper implements SubjectMetadataMapper
{
    private PkpSubjectClassifier $classifier;

    public function __construct(?PkpSubjectClassifier $classifier = null)
    {
        $this->classifier = $classifier ?? new PkpSubjectClassifier();
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        $locale = $publication->getData('locale');
        $subjects = [];
        $seen = [];

        foreach (($publication->getData('subjects') ?? [])[$locale] ?? [] as $subject) {
            $this->append($subjects, $seen, $this->classifier->classify($subject));
        }
        foreach (($publication->getData('keywords') ?? [])[$locale] ?? [] as $keyword) {
            $this->append($subjects, $seen, [
                'subjectType' => SubjectType::KEYWORD,
                'subjectCode' => trim((string) ($keyword['name'] ?? $keyword)),
            ]);
        }

        return $subjects;
    }

    private function append(array &$subjects, array &$seen, array $subject): void
    {
        $subject['subjectType'] = strtoupper(trim((string) ($subject['subjectType'] ?? '')));
        $subject['subjectCode'] = trim((string) ($subject['subjectCode'] ?? ''));
        if ($subject['subjectType'] === '' || $subject['subjectCode'] === '') {
            return;
        }

        $key = $subject['subjectType'] . "\0" . $subject['subjectCode'];
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $subject['subjectOrdinal'] = count($subjects) + 1;
        $subjects[] = $subject;
    }
}

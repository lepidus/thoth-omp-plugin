<?php


final class SubjectSynchronizer implements DomainSynchronizer
{
    private $gateway;
    private $mapper;

    public function __construct(SubjectMetadataGateway $gateway, SubjectMetadataMapper $mapper)
    {
        $this->gateway = $gateway;
        $this->mapper = $mapper;
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $remoteSubjects = $this->normalizeRemoteSubjects($this->gateway->snapshot($workId));
        $desiredSubjects = array_map(
            [$this, 'normalizeSubject'],
            $this->mapper->fromPublication($desiredState, $workId)
        );
        $remainingSubjects = $remoteSubjects;
        $matchedSubjects = [];
        $newSubjects = [];

        foreach ($desiredSubjects as $subject) {
            $key = $this->subjectKey($subject);
            if (!isset($remainingSubjects[$key])) {
                $newSubjects[] = $subject;
                continue;
            }
            $matchedSubjects[] = ['desired' => $subject, 'remote' => $remainingSubjects[$key]];
            unset($remainingSubjects[$key]);
        }

        foreach ($remainingSubjects as $remoteSubject) {
            $this->gateway->delete($remoteSubject['subjectId']);
        }

        $collisionTypes = $this->collisionTypes($desiredSubjects, $remoteSubjects, $matchedSubjects);
        $temporaryOrdinal = $this->nextAvailableOrdinal($desiredSubjects, $remoteSubjects);
        foreach ($matchedSubjects as $match) {
            if ($this->needsUpdate($match) && isset($collisionTypes[$match['desired']['subjectType']])) {
                $temporarySubject = $match['desired'];
                $temporarySubject['subjectOrdinal'] = $temporaryOrdinal++;
                $this->gateway->update($workId, $match['remote']['subjectId'], $temporarySubject);
            }
        }

        foreach ($newSubjects as $subject) {
            $this->gateway->create($workId, $subject);
        }
        foreach ($matchedSubjects as $match) {
            if ($this->needsUpdate($match)) {
                $this->gateway->update($workId, $match['remote']['subjectId'], $match['desired']);
            }
        }

        return new SynchronizationResult();
    }

    private function normalizeRemoteSubjects(array $subjects): array
    {
        $normalizedSubjects = [];
        foreach ($subjects as $subject) {
            $normalizedSubject = $this->normalizeSubject($subject);
            if (!$this->isCompleteRemoteSubject($normalizedSubject)) {
                throw new InvalidRemoteMetadata('synchronizeSubjects', 'incompleteRemoteSubject');
            }
            $key = $this->subjectKey($normalizedSubject);
            if (isset($normalizedSubjects[$key])) {
                throw new InvalidRemoteMetadata('synchronizeSubjects', 'ambiguousRemoteSubject');
            }
            $normalizedSubjects[$key] = $normalizedSubject;
        }

        return $normalizedSubjects;
    }

    private function normalizeSubject(array $subject): array
    {
        $normalized = [
            'subjectType' => strtoupper(trim((string) ($subject['subjectType'] ?? ''))),
            'subjectCode' => trim((string) ($subject['subjectCode'] ?? '')),
            'subjectOrdinal' => (int) ($subject['subjectOrdinal'] ?? 0),
        ];
        if (isset($subject['subjectId'])) {
            $normalized['subjectId'] = trim((string) $subject['subjectId']);
        }

        return $normalized;
    }

    private function isCompleteRemoteSubject(array $subject): bool
    {
        return ($subject['subjectId'] ?? '') !== ''
            && $subject['subjectType'] !== ''
            && $subject['subjectCode'] !== ''
            && $subject['subjectOrdinal'] > 0;
    }

    private function subjectKey(array $subject): string
    {
        return $subject['subjectType'] . "\0" . $subject['subjectCode'];
    }

    private function collisionTypes(array $desiredSubjects, array $remoteSubjects, array $matchedSubjects): array
    {
        $occupiedOrdinals = [];
        foreach ($remoteSubjects as $remoteSubject) {
            $occupiedOrdinals[$remoteSubject['subjectType']][$remoteSubject['subjectOrdinal']] =
                $remoteSubject['subjectId'];
        }
        $matchedIds = [];
        foreach ($matchedSubjects as $match) {
            $matchedIds[$this->subjectKey($match['desired'])] = $match['remote']['subjectId'];
        }
        $collisionTypes = [];
        foreach ($desiredSubjects as $subject) {
            $occupyingId = $occupiedOrdinals[$subject['subjectType']][$subject['subjectOrdinal']] ?? null;
            $matchedId = $matchedIds[$this->subjectKey($subject)] ?? null;
            if ($occupyingId !== null && $occupyingId !== $matchedId) {
                $collisionTypes[$subject['subjectType']] = true;
            }
        }

        return $collisionTypes;
    }

    private function nextAvailableOrdinal(array $desiredSubjects, array $remoteSubjects): int
    {
        $highestOrdinal = 0;
        foreach (array_merge($desiredSubjects, array_values($remoteSubjects)) as $subject) {
            $highestOrdinal = max($highestOrdinal, $subject['subjectOrdinal']);
        }

        return $highestOrdinal + 1;
    }

    private function needsUpdate(array $match): bool
    {
        return $match['desired']['subjectOrdinal'] !== $match['remote']['subjectOrdinal'];
    }
}

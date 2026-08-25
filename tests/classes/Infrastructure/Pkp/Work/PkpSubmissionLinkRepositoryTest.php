<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

final class PkpSubmissionLinkRepositoryTest extends TestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testItReadsWritesAndDeletesThePersistentSubmissionLink(): void
    {
        $submission = $this->createMock(Pkp33SubmissionDouble::class);
        $submission->method('getData')->with('thothWorkId')->willReturn(self::WORK_ID);
        $submission->expects($this->exactly(2))->method('setData')->withConsecutive(
            ['thothWorkId', self::WORK_ID],
            ['thothWorkId', null]
        );
        $submissions = $this->createMock(Pkp33SubmissionDaoDouble::class);
        $submissions->method('getById')->with(17)->willReturn($submission);
        $submissions->expects($this->exactly(2))->method('updateObject')->with($submission);
        $repository = new PkpSubmissionLinkRepository($submissions);
        $submissionId = new SubmissionId(17);
        $workId = new WorkId(self::WORK_ID);

        $storedWorkId = $repository->findWorkId($submissionId);
        $repository->saveWorkId($submissionId, $workId);
        $repository->deleteWorkId($submissionId);

        $this->assertNotNull($storedWorkId);
        $this->assertTrue($workId->equals($storedWorkId));
    }

    public function testItDoesNotHideAMissingSubmissionOnWrite(): void
    {
        $submissions = $this->createMock(Pkp33SubmissionDaoDouble::class);
        $submissions->method('getById')->willReturn(null);
        $submissions->expects($this->never())->method('updateObject');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Submission not found');

        (new PkpSubmissionLinkRepository($submissions))->saveWorkId(
            new SubmissionId(17),
            new WorkId(self::WORK_ID)
        );
    }

    public function testItReturnsNullWhenTheSubmissionOrLinkDoesNotExist(): void
    {
        $submissionWithoutLink = $this->createMock(Pkp33SubmissionDouble::class);
        $submissionWithoutLink->method('getData')->willReturn(null);
        $submissions = $this->createMock(Pkp33SubmissionDaoDouble::class);
        $submissions->method('getById')->willReturnOnConsecutiveCalls(null, $submissionWithoutLink);
        $repository = new PkpSubmissionLinkRepository($submissions);

        $this->assertNull($repository->findWorkId(new SubmissionId(17)));
        $this->assertNull($repository->findWorkId(new SubmissionId(18)));
    }
}

interface Pkp33SubmissionDaoDouble
{
    public function getById(int $submissionId): ?object;

    public function updateObject(object $submission): void;
}

interface Pkp33SubmissionDouble
{
    public function getData(string $name);

    public function setData(string $name, $value): void;
}

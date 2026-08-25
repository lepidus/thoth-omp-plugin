<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Work;

use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Work\PkpSubmissionLinkRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PkpSubmissionLinkRepositoryTest extends TestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testItReadsWritesAndDeletesThePersistentSubmissionLink(): void
    {
        $submission = $this->createMock(SubmissionDouble::class);
        $submission->method('getData')->with('thothWorkId')->willReturn(self::WORK_ID);
        $submissions = $this->createMock(SubmissionRepositoryDouble::class);
        $submissions->method('get')->with(17)->willReturn($submission);
        $edits = [];
        $submissions->expects($this->exactly(2))->method('edit')
            ->willReturnCallback(function (object $editedSubmission, array $params) use (&$edits): void {
                $edits[] = [$editedSubmission, $params];
            });
        $repository = new PkpSubmissionLinkRepository($submissions);
        $submissionId = new SubmissionId(17);
        $workId = new WorkId(self::WORK_ID);

        $storedWorkId = $repository->findWorkId($submissionId);
        $repository->saveWorkId($submissionId, $workId);
        $repository->deleteWorkId($submissionId);

        $this->assertNotNull($storedWorkId);
        $this->assertTrue($workId->equals($storedWorkId));
        $this->assertSame([
            [$submission, ['thothWorkId' => self::WORK_ID]],
            [$submission, ['thothWorkId' => null]],
        ], $edits);
    }

    public function testItDoesNotHideAMissingSubmissionOnWrite(): void
    {
        $submissions = $this->createMock(SubmissionRepositoryDouble::class);
        $submissions->method('get')->willReturn(null);
        $submissions->expects($this->never())->method('edit');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Submission not found');

        (new PkpSubmissionLinkRepository($submissions))->saveWorkId(
            new SubmissionId(17),
            new WorkId(self::WORK_ID)
        );
    }

    public function testItReturnsNullWhenTheSubmissionOrLinkDoesNotExist(): void
    {
        $submissionWithoutLink = $this->createMock(SubmissionDouble::class);
        $submissionWithoutLink->method('getData')->willReturn(null);
        $submissions = $this->createMock(SubmissionRepositoryDouble::class);
        $submissions->method('get')->willReturnOnConsecutiveCalls(null, $submissionWithoutLink);
        $repository = new PkpSubmissionLinkRepository($submissions);

        $this->assertNull($repository->findWorkId(new SubmissionId(17)));
        $this->assertNull($repository->findWorkId(new SubmissionId(18)));
    }
}

interface SubmissionRepositoryDouble
{
    public function get(int $submissionId): ?object;

    public function edit(object $submission, array $params): void;
}

interface SubmissionDouble
{
    public function getData(string $name);
}

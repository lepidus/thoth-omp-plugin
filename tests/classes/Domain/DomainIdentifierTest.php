<?php

namespace APP\plugins\generic\thoth\tests\classes\Domain;

use APP\plugins\generic\thoth\classes\Domain\Imprint\ImprintId;
use APP\plugins\generic\thoth\classes\Domain\Publication\PublicationId;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DomainIdentifierTest extends TestCase
{
    public function testRemoteIdentifiersPreserveAndCompareUuidValues(): void
    {
        $workId = new WorkId('4c64863b-ce51-4cf5-bedf-0dd911147f6d');
        $sameWorkId = new WorkId('4C64863B-CE51-4CF5-BEDF-0DD911147F6D');
        $imprintId = new ImprintId('41b6a2a4-c3e1-4045-882c-c0f31386dee5');

        $this->assertSame('4c64863b-ce51-4cf5-bedf-0dd911147f6d', $workId->toString());
        $this->assertSame($workId->toString(), (string) $workId);
        $this->assertTrue($workId->equals($sameWorkId));
        $this->assertSame('41b6a2a4-c3e1-4045-882c-c0f31386dee5', $imprintId->toString());
    }

    /**
     * @dataProvider invalidRemoteIdentifierProvider
     */
    public function testRemoteIdentifiersRejectInvalidValues(string $identifierClass, string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new $identifierClass($value);
    }

    public static function invalidRemoteIdentifierProvider(): array
    {
        return [
            'empty Work ID' => [WorkId::class, ''],
            'malformed Work ID' => [WorkId::class, 'not-a-uuid'],
            'malformed Imprint ID' => [ImprintId::class, '41b6a2a4'],
        ];
    }

    public function testLocalIdentifiersPreserveAndComparePositiveValues(): void
    {
        $publicationId = new PublicationId(23);
        $submissionId = new SubmissionId(17);

        $this->assertSame(23, $publicationId->toInt());
        $this->assertTrue($publicationId->equals(new PublicationId(23)));
        $this->assertSame(17, $submissionId->toInt());
        $this->assertTrue($submissionId->equals(new SubmissionId(17)));
    }

    /**
     * @dataProvider invalidLocalIdentifierProvider
     */
    public function testLocalIdentifiersRejectNonPositiveValues(string $identifierClass, int $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new $identifierClass($value);
    }

    public static function invalidLocalIdentifierProvider(): array
    {
        return [
            'zero Publication ID' => [PublicationId::class, 0],
            'negative Publication ID' => [PublicationId::class, -1],
            'zero Submission ID' => [SubmissionId::class, 0],
            'negative Submission ID' => [SubmissionId::class, -1],
        ];
    }
}

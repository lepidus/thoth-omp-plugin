<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Work;

use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Contracts\WorkGateway;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

class GetWorkStatusTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    #[DataProvider('statusProvider')]
    public function testReturnsTheStatusProvidedByTheWorkGateway(?string $status): void
    {
        $workId = new WorkId(self::WORK_ID);
        $gateway = $this->createMock(WorkGateway::class);
        $gateway->expects($this->once())->method('getStatus')->with($workId)->willReturn($status);

        $this->assertSame($status, (new GetWorkStatus($gateway))->execute($workId));
    }

    public static function statusProvider(): array
    {
        return [
            'existing Work' => ['ACTIVE'],
            'missing Work' => [null],
        ];
    }
}

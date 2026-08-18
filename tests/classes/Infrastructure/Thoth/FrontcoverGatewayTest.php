<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyFrontcoverGateway');

class FrontcoverGatewayTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testItDelegatesToTheExistingFrontcoverServiceAndTranslatesItsWarning(): void
    {
        $publication = new stdClass();
        $service = new RecordingFrontcoverService('plugins.generic.thoth.frontcover.unsupportedFormat');

        $warning = (new LegacyFrontcoverGateway($service))->synchronize(
            $publication,
            new WorkId(self::WORK_ID)
        );

        $this->assertSame([[$publication, self::WORK_ID]], $service->calls);
        $this->assertSame('plugins.generic.thoth.frontcover.unsupportedFormat', $warning->getMessageKey());
    }
}

final class RecordingFrontcoverService
{
    public array $calls = [];
    private ?string $warning;

    public function __construct(?string $warning)
    {
        $this->warning = $warning;
    }

    public function sync(object $publication, string $workId): ?string
    {
        $this->calls[] = [$publication, $workId];

        return $this->warning;
    }
}

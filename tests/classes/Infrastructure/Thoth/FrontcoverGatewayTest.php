<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyFrontcoverGateway;
use PKP\tests\PKPTestCase;
use stdClass;

final class FrontcoverGatewayTest extends PKPTestCase
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

    public function __construct(private ?string $warning)
    {
    }

    public function sync(object $publication, string $workId): ?string
    {
        $this->calls[] = [$publication, $workId];

        return $this->warning;
    }
}

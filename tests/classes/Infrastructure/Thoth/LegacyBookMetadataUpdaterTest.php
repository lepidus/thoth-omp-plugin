<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyBookMetadataUpdater;
use PKP\tests\PKPTestCase;

class LegacyBookMetadataUpdaterTest extends PKPTestCase
{
    public function testItDelegatesTheCurrentWorkAndMetadataScope(): void
    {
        $service = new LegacyBookMetadataServiceStub();
        $publication = new \stdClass();
        $result = (new LegacyBookMetadataUpdater($service))->update(
            $publication,
            new WorkId('11111111-1111-4111-8111-111111111111'),
            true
        );

        $this->assertSame($publication, $service->publication);
        $this->assertSame('11111111-1111-4111-8111-111111111111', $service->workId);
        $this->assertTrue($service->includedTitlesAndAbstracts);
        $this->assertFalse($result->hasWarnings());
    }

    public function testItMapsTheLegacyWarningToTheSynchronizationResult(): void
    {
        $service = new LegacyBookMetadataServiceStub('plugins.generic.thoth.frontcover.unsupportedFormat');
        $result = (new LegacyBookMetadataUpdater($service))->update(
            new \stdClass(),
            new WorkId('11111111-1111-4111-8111-111111111111'),
            false
        );

        $this->assertSame(
            'plugins.generic.thoth.frontcover.unsupportedFormat',
            $result->getWarnings()[0]->getMessageKey()
        );
    }
}

class LegacyBookMetadataServiceStub
{
    public ?object $publication = null;
    public ?string $workId = null;
    public bool $includedTitlesAndAbstracts = false;
    private ?string $warning;

    public function __construct(?string $warning = null)
    {
        $this->warning = $warning;
    }

    public function update(object $publication, string $workId, bool $includeTitlesAndAbstracts): ?string
    {
        $this->publication = $publication;
        $this->workId = $workId;
        $this->includedTitlesAndAbstracts = $includeTitlesAndAbstracts;
        return $this->warning;
    }
}

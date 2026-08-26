<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');

final class ThothCompositionRootTest extends PKPTestCase
{
    public function testMetadataFactoryBuildsOnlyDefinitiveSynchronizers(): void
    {
        $factory = $this->factory();

        $this->assertInstanceOf(SynchronizeMetadata::class, $factory->synchronizer());
        $this->assertInstanceOf(ThothBookMetadataUpdater::class, $factory->bookMetadataUpdater());
        $this->assertInstanceOf(PkpWorkMetadataMapper::class, $factory->workMetadataMapper());
    }

    public function testMetadataFactoryReusesItsPublicGraph(): void
    {
        $factory = $this->factory();
        $synchronizer = $factory->synchronizer();
        $metadataUpdater = $factory->bookMetadataUpdater();
        $workMapper = $factory->workMetadataMapper();

        $this->assertSame($synchronizer, $factory->synchronizer());
        $this->assertSame($metadataUpdater, $factory->bookMetadataUpdater());
        $this->assertSame($workMapper, $factory->workMetadataMapper());
        $this->assertNotSame($synchronizer, $metadataUpdater);
    }

    private function factory(): MetadataSynchronizationFactory
    {
        return new MetadataSynchronizationFactory(
            new ThothRemoteGateway(new \stdClass(), new ThothErrorTranslator()),
            new PkpWorkMetadataReader(
                new \stdClass(),
                new \stdClass(),
                new \stdClass(),
                new \stdClass(),
                new \stdClass()
            ),
            new PkpPublicationMetadataReader(
                new \stdClass(),
                new \stdClass(),
                new \stdClass(),
                new \stdClass(),
                new \stdClass(),
                new \stdClass()
            ),
            $this->createMock(ContributionAuthorReader::class),
            new \stdClass(),
            new \stdClass(),
            new ThothPresignedFileUploader(new \stdClass(), new ThothApiUrlGuard()),
            $this->createMock(FrontcoverLocalGateway::class)
        );
    }
}

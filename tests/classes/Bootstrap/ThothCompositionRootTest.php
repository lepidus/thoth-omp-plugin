<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');

final class ThothCompositionRootTest extends PKPTestCase
{
    public function testMetadataFactoryBuildsOnlyDefinitiveSynchronizers(): void
    {
        $factory = new MetadataSynchronizationFactory(
            new ThothRemoteGateway(new \stdClass(), new ThothErrorTranslator()),
            $this->withoutConstructor(PkpWorkMetadataReader::class),
            $this->withoutConstructor(PkpPublicationMetadataReader::class),
            $this->createMock(ContributionAuthorReader::class),
            new \stdClass(),
            new \stdClass(),
            $this->withoutConstructor(ThothPresignedFileUploader::class),
            $this->createMock(FrontcoverLocalGateway::class)
        );

        $this->assertInstanceOf(SynchronizeMetadata::class, $factory->synchronizer());
        $this->assertInstanceOf(ThothBookMetadataUpdater::class, $factory->bookMetadataUpdater());
        $this->assertInstanceOf(PkpWorkMetadataMapper::class, $factory->workMetadataMapper());
    }

    public function testBootstrapSourcesContainNoContainerOrObsoleteResolution(): void
    {
        $root = dirname(__DIR__, 3);
        $sources = file_get_contents($root . '/classes/Bootstrap/MetadataSynchronizationFactory.inc.php')
            . file_get_contents($root . '/classes/Bootstrap/ThothCompositionRoot.inc.php')
            . file_get_contents($root . '/classes/Bootstrap/PluginBootstrap.inc.php')
            . file_get_contents($root . '/ThothPlugin.php');

        $this->assertStringNotContainsString('PKP' . 'Container', $sources);
        $this->assertStringNotContainsString('Leg' . 'acy', $sources);
        $this->assertStringNotContainsString('->' . 'make(', $sources);
        $this->assertStringNotContainsString('->' . 'bind(', $sources);
    }

    private function withoutConstructor(string $class): object
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}

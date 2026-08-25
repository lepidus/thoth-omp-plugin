<?php

namespace APP\plugins\generic\thoth\tests\classes\Bootstrap;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\ContributionAuthorReader;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\FrontcoverLocalGateway;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeMetadata;
use APP\plugins\generic\thoth\classes\Bootstrap\MetadataSynchronizationFactory;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Publications\PkpPublicationMetadataReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Works\PkpWorkMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Works\PkpWorkMetadataReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\FailureReporting\ThothErrorTranslator;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets\ThothPresignedFileUploader;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\ThothBookMetadataUpdater;
use PKP\tests\PKPTestCase;
use ReflectionClass;

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
        $sources = file_get_contents($root . '/classes/Bootstrap/MetadataSynchronizationFactory.php')
            . file_get_contents($root . '/classes/Bootstrap/ThothCompositionRoot.php')
            . file_get_contents($root . '/classes/Bootstrap/PluginBootstrap.php')
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

<?php

require_once(__DIR__ . '/../../../../vendor/autoload.php');

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Contracts.WorkMetadataGateway');
import('plugins.generic.thoth.classes.Contracts.WorkMetadataMapper');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyWorkMetadataGateway');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyWorkMetadataMapper');

class WorkMetadataAdaptersTest extends PKPTestCase
{
    public const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testGatewayUsesAMinimalWorkSnapshot(): void
    {
        $repository = new class () {
            public function get($workId)
            {
                return new class () {
                    public function toArray(): array
                    {
                        return [
                            'workId' => WorkMetadataAdaptersTest::WORK_ID,
                            'workStatus' => 'ACTIVE',
                            'doi' => 'https://doi.org/10.1234/book',
                            'fullTitle' => 'Remote title',
                            'titles' => [['titleId' => 'title-id']],
                            'abstracts' => [['abstractId' => 'abstract-id']],
                        ];
                    }
                };
            }
        };

        $snapshot = (new LegacyWorkMetadataGateway($repository))->snapshot(new WorkId(self::WORK_ID));

        $this->assertSame([
            'workId' => self::WORK_ID,
            'workStatus' => 'ACTIVE',
            'doi' => 'https://doi.org/10.1234/book',
        ], $snapshot);
    }

    public function testGatewayBuildsThePatchWithThePersistentWorkId(): void
    {
        $repository = new class () {
            public array $metadata = [];
            public object $patch;
            public object $edited;

            public function new(array $metadata): object
            {
                $this->metadata = $metadata;
                $this->patch = new stdClass();
                return $this->patch;
            }

            public function edit(object $patch): void
            {
                $this->edited = $patch;
            }
        };
        $gateway = new LegacyWorkMetadataGateway($repository);

        $gateway->update(new WorkId(self::WORK_ID), ['doi' => 'https://doi.org/10.1234/new']);

        $this->assertSame([
            'doi' => 'https://doi.org/10.1234/new',
            'workId' => self::WORK_ID,
        ], $repository->metadata);
        $this->assertSame($repository->patch, $repository->edited);
    }

    public function testMapperReturnsOnlyTheFactoryWorkData(): void
    {
        $publication = new stdClass();
        $factory = new class () {
            public function createFromPublication(object $publication): object
            {
                return new class () {
                    public function getAllData(): array
                    {
                        return ['workStatus' => 'FORTHCOMING', 'doi' => 'https://doi.org/10.1234/book'];
                    }
                };
            }
        };

        $metadata = (new LegacyWorkMetadataMapper($factory))->fromPublication($publication);

        $this->assertSame([
            'workStatus' => 'FORTHCOMING',
            'doi' => 'https://doi.org/10.1234/book',
        ], $metadata);
    }
}

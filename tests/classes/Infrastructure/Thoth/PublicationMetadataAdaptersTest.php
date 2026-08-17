<?php

require_once(__DIR__ . '/../../../../vendor/autoload.php');

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyPublicationMetadataGateway');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyPublicationMetadataMapper');
use ThothApi\GraphQL\Inputs\PatchLocation;
use ThothApi\GraphQL\Inputs\PatchPublication;

final class PublicationMetadataAdaptersTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testMapperKeepsOnlyDesiredMetadataAndRequestLocalCollaborators(): void
    {
        $publicationFormat = $this->publicationFormat();
        $submissionFile = new \stdClass();
        $location = new PatchLocation(['fullTextUrl' => 'https://example.com/book.pdf']);
        $factory = new class () {
            public function createFromPublicationFormat($publicationFormat, $submissionFile): PatchPublication
            {
                return new PatchPublication([
                    'publicationId' => 'must-not-cross-the-mapper',
                    'workId' => 'must-not-cross-the-mapper',
                    'publicationType' => 'PDF',
                    'isbn' => '978-3-16-148410-0',
                    'accessibilityStandard' => 'WCAG21AA',
                ]);
            }
        };
        $locationService = new class ($location) {
            private $location;

            public function __construct($location)
            {
                $this->location = $location;
            }

            public function getDesiredByPublicationFormat($publicationFormat, array $files): array
            {
                return [$this->location];
            }
        };
        $service = new class ($publicationFormat, $submissionFile, $factory, $locationService) {
            public $factory;
            public $locationService;
            private $publicationFormat;
            private $submissionFile;

            public function __construct($publicationFormat, $submissionFile, $factory, $locationService)
            {
                $this->publicationFormat = $publicationFormat;
                $this->submissionFile = $submissionFile;
                $this->factory = $factory;
                $this->locationService = $locationService;
            }

            public function getBookPublicationData($publication): array
            {
                return [[$this->publicationFormat], [42 => [$this->submissionFile]]];
            }

            public function canRegister($publicationFormat, $submissionFile): bool
            {
                return true;
            }
        };

        $mapped = (new LegacyPublicationMetadataMapper($service))->fromPublication(
            new \stdClass(),
            $this->workId()
        );

        $this->assertSame($publicationFormat, $mapped[0]['publicationFormat']);
        $this->assertSame([$location], $mapped[0]['locations']);
        $this->assertSame('PDF', $mapped[0]['publicationType']);
        $this->assertSame('978-3-16-148410-0', $mapped[0]['isbn']);
        $this->assertArrayNotHasKey('publicationId', $mapped[0]);
        $this->assertArrayNotHasKey('workId', $mapped[0]);
    }

    public function testGatewayUsesRemoteIdsOnlyForCurrentPublicationAndLocationMutations(): void
    {
        $repository = new class () {
            public array $added = [];
            public array $edited = [];
            public array $deleted = [];

            public function getByWorkId(string $workId): array
            {
                return ['workStatus' => 'FORTHCOMING', 'publications' => []];
            }

            public function new(array $metadata): PatchPublication
            {
                return new PatchPublication($metadata);
            }

            public function add(PatchPublication $publication): string
            {
                $this->added[] = $publication;

                return 'new-publication-id';
            }

            public function edit(PatchPublication $publication): void
            {
                $this->edited[] = $publication;
            }

            public function delete(string $publicationId): void
            {
                $this->deleted[] = $publicationId;
            }
        };
        $locationService = new class () {
            public array $updates = [];

            public function update(string $publicationId, array $desired, array $remote): void
            {
                $this->updates[] = [$publicationId, $desired, $remote];
            }
        };
        $service = new class ($repository, $locationService) {
            public $repository;
            public $locationService;

            public function __construct($repository, $locationService)
            {
                $this->repository = $repository;
                $this->locationService = $locationService;
            }
        };
        $format = $this->publicationFormat();
        $desiredLocation = new PatchLocation(['fullTextUrl' => 'https://example.com/book.pdf']);
        $metadata = [
            'publicationFormat' => $format,
            'publicationType' => 'PDF',
            'isbn' => null,
            'locations' => [$desiredLocation],
        ];
        $gateway = new LegacyPublicationMetadataGateway($service);

        $this->assertSame('FORTHCOMING', $gateway->snapshot($this->workId())['workStatus']);
        $gateway->create($this->workId(), $metadata);
        $gateway->update(
            $this->workId(),
            'existing-publication-id',
            $metadata,
            ['locations' => [['locationId' => 'remote-location-id']]],
            true
        );
        $gateway->delete('obsolete-publication-id');

        $this->assertSame(self::WORK_ID, $repository->added[0]->getWorkId());
        $this->assertSame('existing-publication-id', $repository->edited[0]->getPublicationId());
        $this->assertSame(self::WORK_ID, $repository->edited[0]->getWorkId());
        $this->assertSame(['obsolete-publication-id'], $repository->deleted);
        $this->assertSame('new-publication-id', $locationService->updates[0][0]);
        $this->assertSame('existing-publication-id', $locationService->updates[1][0]);
        $this->assertSame('remote-location-id', $locationService->updates[1][2][0]['locationId']);
        $this->assertSame('existing-publication-id', $format->thothPublicationId);
    }

    private function publicationFormat(): object
    {
        return new class () {
            public ?string $thothPublicationId = null;

            public function getId(): int
            {
                return 42;
            }

            public function setData(string $key, $value): void
            {
                if ($key === 'thothPublicationId') {
                    $this->thothPublicationId = $value;
                }
            }
        };
    }

    private function workId(): WorkId
    {
        return new WorkId(self::WORK_ID);
    }
}

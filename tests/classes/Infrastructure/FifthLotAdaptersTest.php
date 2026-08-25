<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Application\Catalog\GetCatalogFiles;
use APP\plugins\generic\thoth\classes\Application\Catalog\Port\CatalogFileGateway;
use APP\plugins\generic\thoth\classes\Domain\Imprint\Imprint;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Catalog\PkpCatalogPublicationFilesProvider;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpPublicationFileFormReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpTemporaryUploadReceiver;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Submission\PkpSubmissionListProvider;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Submission\PkpSubmissionReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Submission\PkpSubmissionResponseMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\FailureReporting\ThothErrorTranslator;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets\ThothFeatureVideoReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Registration\ThothPublisherAccessGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Registration\ThothRegistrationMetadataValidator;
use APP\submission\Submission;
use PKP\tests\PKPTestCase;
use ThothApi\Exception\QueryException;

final class FifthLotAdaptersTest extends PKPTestCase
{
    public function testSubmissionResponseUsesTheOfficialSchemaMapContext(): void
    {
        $submission = new \stdClass();
        $schemaMap = new class () {
            public array $arguments = [];

            public function map(...$arguments): array
            {
                $this->arguments = $arguments;
                return ['id' => 19];
            }
        };
        $mapper = new PkpSubmissionResponseMapper($schemaMap, ['group'], ['genre'], [16]);

        $this->assertSame(['id' => 19], $mapper->map($submission));
        $this->assertSame([$submission, ['group'], ['genre'], [16]], $schemaMap->arguments);
    }

    public function testSubmissionReaderUsesTheExplicitPkpRepository(): void
    {
        $submission = new FifthLotDataObject(19);
        $reader = new PkpSubmissionReader(new FifthLotGetRepository([$submission]));

        $this->assertSame($submission, $reader->find(new SubmissionId(19)));
        $this->assertNull($reader->find(new SubmissionId(20)));
    }

    public function testTemporaryUploadReceiverReturnsOnlyTheAcceptedFileId(): void
    {
        $manager = new class () {
            public array $arguments = [];
            public $result;

            public function handleUpload(...$arguments)
            {
                $this->arguments = $arguments;
                return $this->result;
            }
        };
        $manager->result = new class () {
            public function getId(): int
            {
                return 73;
            }
        };
        $receiver = new PkpTemporaryUploadReceiver($manager);

        $this->assertSame(73, $receiver->receive('uploadedFile', 41));
        $this->assertSame(['uploadedFile', 41], $manager->arguments);
        $manager->result = null;
        $this->assertNull($receiver->receive('uploadedFile', 41));
    }

    public function testPublicationFileFormReaderValidatesOwnershipAndBuildsDoiComponents(): void
    {
        $publication = new FifthLotDataObject(31, [
            'submissionId' => 21,
            'doiObject' => new class () {
                public function getResolvingUrl(): string
                {
                    return 'https://doi.org/10.1234/book';
                }
            },
            'title' => 'The Book',
        ]);
        $submission = new FifthLotDataObject(21, ['contextId' => 7, 'thothWorkId' => 'work-id']);
        $chapter = new FifthLotDataObject(41, ['doi' => '10.1234/chapter', 'title' => 'The Chapter']);
        $reader = new PkpPublicationFileFormReader(
            new FifthLotGetRepository([$publication]),
            new FifthLotGetRepository([$submission]),
            new class () {
                public function getById(int $id, int $publicationId): object
                {
                    return new \stdClass();
                }
            },
            new class ($chapter) {
                public function __construct(private object $chapter)
                {
                }

                public function getByPublicationId(int $publicationId): object
                {
                    return new FifthLotResultSet([$this->chapter]);
                }
            }
        );

        $context = $reader->read(7, 31, 51, 'work-id');

        $this->assertSame(21, $context->submissionId()->toInt());
        $this->assertFalse($context->missingDoi());
        $this->assertTrue($context->acceptsComponent(31));
        $this->assertTrue($context->acceptsComponent(41));
        $this->assertCount(2, $context->components());
    }

    public function testSubmissionListProviderScopesAndMapsTheOfficialCollector(): void
    {
        $collector = new FifthLotSubmissionCollector([new FifthLotDataObject(91)]);
        $schemaMap = new class () {
            public array $arguments = [];

            public function mapMany(...$arguments): object
            {
                $this->arguments = $arguments;
                return collect([['id' => 91]]);
            }
        };
        $submissions = new class ($collector, $schemaMap) {
            public function __construct(private object $collector, private object $schemaMap)
            {
            }

            public function getCollector(): object
            {
                return $this->collector;
            }

            public function getSchemaMap(): object
            {
                return $this->schemaMap;
            }
        };
        $category = new FifthLotCatalogObject(3, 'OA', 'title-asc');
        $series = new FifthLotCatalogObject(4, 'History', 'date-desc');
        $provider = new PkpSubmissionListProvider(
            $submissions,
            new FifthLotCollectorRepository([$category]),
            new FifthLotCollectorRepository([$series]),
            new class () {
                public function getByContextId(int $contextId): object
                {
                    return new FifthLotResultSet(['genre']);
                }
            },
            fn (int $contextId): array => ['group'],
            [16]
        );

        $result = $provider->get(7, 44, 30);

        $this->assertSame([7], $collector->contextIds);
        $this->assertSame([44], $collector->assignedUserIds);
        $this->assertSame(30, $collector->limit);
        $this->assertSame([['id' => 91]], $result['items']);
        $this->assertSame(1, $result['itemsMax']);
        $this->assertSame('categoryIds', $result['filters'][0]['filters'][0]['param']);
        $this->assertSame('seriesIds', $result['filters'][1]['filters'][0]['param']);
    }

    public function testFeatureVideoReaderReturnsTypedClientDataWithoutLeakingTheClient(): void
    {
        $video = new class () {
            public function toArray(): array
            {
                return ['title' => 'Trailer', 'url' => 'https://cdn.example.test/trailer.mp4'];
            }
        };
        $client = new FifthLotRemoteClient();
        $client->results['work'] = new class ($video) {
            public function __construct(private object $video)
            {
            }

            public function getFeaturedVideo(): object
            {
                return $this->video;
            }
        };
        $reader = new ThothFeatureVideoReader($this->remote($client));

        $this->assertSame(
            ['title' => 'Trailer', 'url' => 'https://cdn.example.test/trailer.mp4'],
            $reader->find(new WorkId('55a80f83-8c6b-4a4d-a150-50bf5153dc3b'))
        );
        $this->assertSame('work', $client->calls[0][0]);
        $this->assertSame('55a80f83-8c6b-4a4d-a150-50bf5153dc3b', $client->calls[0][1][0]);
    }

    public function testPublisherAccessFlattensImprintsAndCdnPermission(): void
    {
        $press = new class () {
            public function getImprints(): array
            {
                return [new class () {
                    public function getImprintId(): string
                    {
                        return '90228a3d-c252-42f3-a5bd-a7a22d1b399a';
                    }

                    public function getImprintName(): string
                    {
                        return 'Example Press';
                    }
                }];
            }
        };
        $context = new class ($press) {
            public function __construct(private object $press)
            {
            }

            public function getPublisher(): object
            {
                return $this->press;
            }

            public function getPermissions(): object
            {
                return new class () {
                    public function getCdnWrite(): bool
                    {
                        return true;
                    }
                };
            }
        };
        $client = new FifthLotRemoteClient();
        $client->results['me'] = new class ($context) {
            public function __construct(private object $context)
            {
            }

            public function getPublisherContexts(): array
            {
                return [$this->context];
            }
        };
        $gateway = new ThothPublisherAccessGateway($this->remote($client));

        $this->assertTrue($gateway->canUploadFiles());
        $imprints = $gateway->imprints();
        $this->assertContainsOnlyInstancesOf(Imprint::class, $imprints);
        $this->assertSame('90228a3d-c252-42f3-a5bd-a7a22d1b399a', $imprints[0]->id()->toString());
        $this->assertSame('Example Press', $imprints[0]->name());
    }

    public function testRegistrationValidatorReportsRemoteConflictsAndInvalidIsbn(): void
    {
        $workMetadata = new class () {
            public function fromPublication(object $publication): array
            {
                return [
                    'doi' => 'https://doi.org/10.1234/book',
                    'landingPage' => 'https://press.example.test/catalog/book/1',
                ];
            }
        };
        $format = new FifthLotPublicationFormat(51, 'invalid-isbn', 'PDF');
        $client = new FifthLotRemoteClient();
        $client->results['workByDoi'] = new \stdClass();
        $client->results['works'] = [new class () {
            public function getLandingPage(): string
            {
                return 'https://press.example.test/catalog/book/1';
            }
        }];
        $client->results['publications'] = [new \stdClass()];
        $validator = new ThothRegistrationMetadataValidator(
            $workMetadata,
            new class ($format) {
                public function __construct(private object $format)
                {
                }

                public function getByPublicationId(int $publicationId): array
                {
                    return [$this->format];
                }
            },
            $this->remote($client)
        );

        $errors = $validator->validate(new FifthLotDataObject(31));

        $this->assertCount(4, $errors);
        $this->assertSame(['workByDoi', 'works', 'publications'], array_column($client->calls, 0));
    }

    public function testRegistrationValidatorTreatsOnlyConfirmedMissingDoiAsAvailable(): void
    {
        $client = new FifthLotRemoteClient();
        $client->failures['workByDoi'] = new QueryException(
            ['message' => 'No record was found for the given ID.'],
            null,
            null,
            null,
            200
        );
        $client->results['works'] = [];
        $validator = new ThothRegistrationMetadataValidator(
            new class () {
                public function fromPublication(object $publication): array
                {
                    return ['doi' => 'https://doi.org/10.1234/available'];
                }
            },
            new class () {
                public function getByPublicationId(int $publicationId): array
                {
                    return [];
                }
            },
            $this->remote($client)
        );

        $this->assertSame([], $validator->validate(new FifthLotDataObject(31)));
    }

    public function testCatalogProviderValidatesPublicOwnershipCachesAndMapsRepresentation(): void
    {
        $submission = new FifthLotDataObject(21, [
            'contextId' => 7,
            'thothWorkId' => '55a80f83-8c6b-4a4d-a150-50bf5153dc3b',
        ]);
        $publication = new FifthLotDataObject(31, [
            'submissionId' => 21,
            'status' => Submission::STATUS_PUBLISHED,
            'title' => 'The Book',
        ]);
        $format = new FifthLotPublicationFormat(51, null, 'PDF');
        $format->data['publicationId'] = 31;
        $fileGateway = new class () implements CatalogFileGateway {
            public int $calls = 0;

            public function getByWorkId(WorkId $workId): array
            {
                $this->calls++;
                return [[
                    'url' => 'https://cdn.example.test/book.pdf',
                    'publicationType' => 'PDF',
                ]];
            }
        };
        $cache = new FifthLotCache();
        $provider = new PkpCatalogPublicationFilesProvider(
            new FifthLotGetRepository([$submission]),
            new FifthLotGetRepository([$publication]),
            new FifthLotSubmissionFileRepository([]),
            new class () {
                public function getByPublicationId(int $publicationId): object
                {
                    return new FifthLotResultSet([]);
                }
            },
            new class ($format) {
                public function __construct(private object $format)
                {
                }

                public function getByPublicationId(int $publicationId): array
                {
                    return [$this->format];
                }

                public function getById(int $id, ?int $publicationId = null): ?object
                {
                    return $id === $this->format->getId() ? $this->format : null;
                }
            },
            new class () {
                public function publicationTypeForFormat(object $format, ?object $file = null): string
                {
                    return 'PDF';
                }
            },
            new GetCatalogFiles($fileGateway),
            $this->remote(new FifthLotRemoteClient()),
            $cache
        );

        $catalog = $provider->publicFiles(7, 21, 31);
        $cached = $provider->publicFiles(7, 21, 31);

        $this->assertSame(51, $catalog['monograph'][0]['representationId']);
        $this->assertSame($catalog, $cached);
        $this->assertSame(1, $fileGateway->calls);
        $this->assertSame(3600, $provider->clientCache(31)['ttl']);
        $this->assertCount(1, $provider->formatFiles(7, 31, 51));
    }

    private function remote(object $client): ThothRemoteGateway
    {
        return new ThothRemoteGateway($client, new ThothErrorTranslator());
    }
}

final class FifthLotRemoteClient
{
    public array $results = [];
    public array $failures = [];
    public array $calls = [];

    public function __call(string $name, array $arguments)
    {
        $this->calls[] = [$name, $arguments];
        if (isset($this->failures[$name])) {
            throw $this->failures[$name];
        }
        return $this->results[$name] ?? null;
    }
}

final class FifthLotDataObject
{
    public function __construct(private int $id, private array $data = [])
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getData(string $key)
    {
        return $this->data[$key] ?? null;
    }

    public function getStoredPubId(string $type)
    {
        return $this->data[$type] ?? null;
    }

    public function getLocalizedTitle(): string
    {
        return (string) ($this->data['title'] ?? '');
    }
}

final class FifthLotGetRepository
{
    private array $objects = [];

    public function __construct(array $objects)
    {
        foreach ($objects as $object) {
            $this->objects[$object->getId()] = $object;
        }
    }

    public function get(int $id): ?object
    {
        return $this->objects[$id] ?? null;
    }
}

final class FifthLotResultSet
{
    public function __construct(private array $items)
    {
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function toAssociativeArray(): array
    {
        return $this->items;
    }
}

final class FifthLotSubmissionCollector
{
    public array $contextIds = [];
    public array $assignedUserIds = [];
    public int $limit = 0;

    public function __construct(private array $items)
    {
    }

    public function filterByContextIds(array $ids): self
    {
        $this->contextIds = $ids;
        return $this;
    }

    public function assignedTo(array $ids): self
    {
        $this->assignedUserIds = $ids;
        return $this;
    }

    public function getCount(): int
    {
        return count($this->items);
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function getMany(): array
    {
        return $this->items;
    }
}

final class FifthLotCollectorRepository
{
    public function __construct(private array $items)
    {
    }

    public function getCollector(): object
    {
        return new class ($this->items) {
            public function __construct(private array $items)
            {
            }

            public function filterByContextIds(array $ids): self
            {
                return $this;
            }

            public function getMany(): array
            {
                return $this->items;
            }
        };
    }
}

final class FifthLotCatalogObject
{
    public function __construct(private int $id, private string $title, private string $sort)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLocalizedTitle(): string
    {
        return $this->title;
    }

    public function getSortOption(): string
    {
        return $this->sort;
    }
}

final class FifthLotPublicationFormat
{
    public array $data = [];

    public function __construct(private int $id, private ?string $isbn, private string $name)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLocalizedName(): string
    {
        return $this->name;
    }

    public function getData(string $key)
    {
        return $this->data[$key] ?? null;
    }

    public function getIdentificationCodes(): object
    {
        $isbn = $this->isbn;
        return new FifthLotResultSet($isbn === null ? [] : [new class ($isbn) {
            public function __construct(private string $isbn)
            {
            }

            public function getCode(): string
            {
                return '15';
            }

            public function getValue(): string
            {
                return $this->isbn;
            }
        }]);
    }
}

final class FifthLotSubmissionFileRepository
{
    public function __construct(private array $files)
    {
    }

    public function getCollector(): object
    {
        return new class ($this->files) {
            public function __construct(private array $files)
            {
            }

            public function filterBySubmissionIds(array $ids): self
            {
                return $this;
            }

            public function filterByAssoc(...$args): self
            {
                return $this;
            }

            public function getMany(): array
            {
                return $this->files;
            }
        };
    }
}

final class FifthLotCache
{
    public array $values = [];

    public function get(string $key)
    {
        return $this->values[$key] ?? null;
    }

    public function put(string $key, $value, int $ttl): void
    {
        $this->values[$key] = $value;
    }
}

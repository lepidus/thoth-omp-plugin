<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth\Synchronization;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Application\Synchronization\ChapterSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\ChapterMetadataGateway;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\FrontcoverLocalGateway;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\WorkMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothApiUrlGuard;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\FailureReporting\ThothErrorTranslator;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets\ThothPresignedFileUploader;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Contributions\ThothContributionMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Frontcover\ThothFrontcoverGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Relations\ThothWorkRelationMetadataGateway;
use PHPUnit\Framework\TestCase;
use ThothApi\GraphQL\Inputs\NewAffiliation;
use ThothApi\GraphQL\Inputs\NewBiography;
use ThothApi\GraphQL\Inputs\NewContribution;
use ThothApi\GraphQL\Inputs\NewContributor;
use ThothApi\GraphQL\Inputs\NewFrontcoverFileUpload;
use ThothApi\GraphQL\Inputs\NewWorkRelation;
use ThothApi\GraphQL\Inputs\PatchContribution;
use ThothApi\GraphQL\Inputs\PatchContributor;
use ThothApi\GraphQL\Inputs\PatchWork;
use ThothApi\GraphQL\Inputs\PatchWorkRelation;
use ThothApi\GraphQL\Schemas\Contribution;
use ThothApi\GraphQL\Schemas\Contributor;
use ThothApi\GraphQL\Schemas\File;
use ThothApi\GraphQL\Schemas\FileUploadResponse;
use ThothApi\GraphQL\Schemas\Institution;
use ThothApi\GraphQL\Schemas\Me;
use ThothApi\GraphQL\Schemas\Work;

final class ThothComplexMetadataGatewaysTest extends TestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testContributionGatewayCreatesAndUpdatesContributorAndContribution(): void
    {
        $client = new RecordingComplexMetadataClient();
        $client->responses['contributors'] = [];
        $client->responses['createContributor'] = new Contributor(['contributorId' => 'contributor-id']);
        $client->responses['createContribution'] = new Contribution(['contributionId' => 'contribution-id']);
        $client->responses['institutions'] = [new Institution(['institutionId' => 'institution-id'])];
        $gateway = new ThothContributionMetadataGateway($this->remote($client));
        $metadata = $this->contributionMetadata();

        $gateway->create(new WorkId(self::WORK_ID), $metadata);
        $gateway->update(new WorkId(self::WORK_ID), 'contribution-id', $metadata, [
            'contributorId' => 'contributor-id',
        ], true);
        $gateway->delete('obsolete-contribution');

        $this->assertInstanceOf(NewContributor::class, $client->inputFor('createContributor'));
        $this->assertInstanceOf(NewContribution::class, $client->inputFor('createContribution'));
        $this->assertInstanceOf(NewBiography::class, $client->inputFor('createBiography'));
        $this->assertInstanceOf(NewAffiliation::class, $client->inputFor('createAffiliation'));
        $this->assertInstanceOf(PatchContributor::class, $client->inputFor('updateContributor'));
        $this->assertInstanceOf(PatchContribution::class, $client->inputFor('updateContribution'));
        $this->assertSame(self::WORK_ID, $client->inputFor('createContribution')->getWorkId());
        $this->assertSame(
            [1, null, 'https://orcid.org/0000-0000-0000-0001', null, ['contributorId']],
            $client->argumentsFor('contributors')
        );
        $this->assertSame(['obsolete-contribution'], $client->argumentsFor('deleteContribution'));
    }

    public function testContributionGatewayReturnsCompleteSnapshot(): void
    {
        $client = new RecordingComplexMetadataClient();
        $client->responses['work'] = new Work(['contributions' => [[
            'contributionId' => 'contribution-id',
            'contributionType' => 'AUTHOR',
            'contributionOrdinal' => 1,
            'fullName' => 'Ada Lovelace',
        ]]]);
        $gateway = new ThothContributionMetadataGateway($this->remote($client));

        $snapshot = $gateway->snapshot(new WorkId(self::WORK_ID));

        $this->assertSame('contribution-id', $snapshot[0]['contributionId']);
        $this->assertSame('Ada Lovelace', $snapshot[0]['fullName']);
    }

    public function testWorkRelationGatewayCoordinatesTypedRemoteOperationsAndChapterSynchronizer(): void
    {
        $client = new RecordingComplexMetadataClient();
        $client->responses['work'] = new Work(['imprintId' => 'imprint-id', 'relations' => []]);
        $chapterGateway = new RecordingChapterGateway('f5ef15f6-c1ad-4876-862d-77fcc69ee2d7');
        $chapter = new ChapterSynchronizer(
            $chapterGateway,
            new FixedWorkMapper(),
            new SuccessfulSynchronizer(),
            []
        );
        $gateway = new ThothWorkRelationMetadataGateway($this->remote($client), $chapter);
        $state = new \stdClass();

        $this->assertSame('imprint-id', $gateway->snapshot(new WorkId(self::WORK_ID))['imprintId']);
        $gateway->create(new WorkId(self::WORK_ID), ['chapterState' => $state, 'relationOrdinal' => 2]);
        $this->assertFalse($gateway->updateRelatedWork(
            ['chapterState' => $state],
            ['relatedWork' => ['workId' => 'f5ef15f6-c1ad-4876-862d-77fcc69ee2d7']]
        ));
        $remote = ['workRelationId' => 'relation-id', 'relatorWorkId' => self::WORK_ID,
            'relatedWorkId' => 'f5ef15f6-c1ad-4876-862d-77fcc69ee2d7', 'relationType' => 'HAS_CHILD'];
        $gateway->updateOrdinal($remote, 3);
        $gateway->delete('relation-id', 'f5ef15f6-c1ad-4876-862d-77fcc69ee2d7');

        $this->assertInstanceOf(NewWorkRelation::class, $client->inputFor('createWorkRelation'));
        $this->assertInstanceOf(PatchWorkRelation::class, $client->inputFor('updateWorkRelation'));
        $this->assertSame(['relation-id'], $client->argumentsFor('deleteWorkRelation'));
        $this->assertCount(1, $chapterGateway->deleted);
    }

    public function testFrontcoverGatewayUploadsSupportedFileAndPersistsRemoteUrl(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'thoth-cover-');
        file_put_contents($path, 'jpeg-content');
        $client = new RecordingComplexMetadataClient();
        $client->responses['me'] = new Me(['publisherContexts' => [[
            'permissions' => ['cdnWrite' => true],
        ]]]);
        $client->responses['initFrontcoverFileUpload'] = new FileUploadResponse([
            'fileUploadId' => 'upload-id',
            'uploadUrl' => 'https://uploads.example.test/cover',
            'uploadHeaders' => [],
        ]);
        $client->responses['completeFileUpload'] = new File([
            'cdnUrl' => 'https://cdn.example.test/cover.jpg',
        ]);
        $local = new RecordingFrontcoverLocalGateway($path);
        $http = new RecordingHttpClient();
        $gateway = new ThothFrontcoverGateway(
            $this->remote($client),
            new ThothPresignedFileUploader($http, new ThothApiUrlGuard(fn (): array => ['8.8.8.8'])),
            $local
        );

        try {
            $this->assertNull($gateway->synchronize(new \stdClass(), new WorkId(self::WORK_ID)));
        } finally {
            unlink($path);
        }

        $this->assertInstanceOf(NewFrontcoverFileUpload::class, $client->inputFor('initFrontcoverFileUpload'));
        $this->assertInstanceOf(PatchWork::class, $client->inputFor('updateWork'));
        $this->assertSame('https://cdn.example.test/cover.jpg', $local->savedUrl);
        $this->assertSame('PUT', $http->method);
    }

    public function testFrontcoverGatewayDisablesUnsupportedFileAndReturnsWarning(): void
    {
        $client = new RecordingComplexMetadataClient();
        $client->responses['me'] = new Me(['publisherContexts' => [[
            'permissions' => ['cdnWrite' => true],
        ]]]);
        $local = new RecordingFrontcoverLocalGateway('/tmp/cover.png', 'png', 'image/png');
        $gateway = new ThothFrontcoverGateway(
            $this->remote($client),
            new ThothPresignedFileUploader(
                new RecordingHttpClient(),
                new ThothApiUrlGuard(fn (): array => ['8.8.8.8'])
            ),
            $local
        );

        $warning = $gateway->synchronize(new \stdClass(), new WorkId(self::WORK_ID));

        $this->assertSame(ThothFrontcoverGateway::UNSUPPORTED_FORMAT_WARNING, $warning?->getMessageKey());
        $this->assertTrue($local->disabled);
    }

    private function contributionMetadata(): array
    {
        return [
            'contributionType' => 'AUTHOR',
            'mainContribution' => true,
            'contributionOrdinal' => 1,
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'fullName' => 'Ada Lovelace',
            'orcid' => 'https://orcid.org/0000-0000-0000-0001',
            'author' => new ContributionAuthorStub(),
        ];
    }

    private function remote(object $client): ThothRemoteGateway
    {
        return new ThothRemoteGateway($client, new ThothErrorTranslator());
    }
}

final class RecordingComplexMetadataClient
{
    public array $operations = [];
    public array $responses = [];

    public function __call(string $operation, array $arguments)
    {
        $this->operations[] = ['operation' => $operation, 'arguments' => $arguments];
        return $this->responses[$operation] ?? null;
    }

    public function inputFor(string $operation): object
    {
        foreach ($this->operations as $recorded) {
            if ($recorded['operation'] === $operation) {
                foreach ($recorded['arguments'] as $argument) {
                    if (is_object($argument) && method_exists($argument, 'getAllData')) {
                        return $argument;
                    }
                }
            }
        }
        throw new \RuntimeException("No input recorded for {$operation}");
    }

    public function argumentsFor(string $operation): array
    {
        foreach ($this->operations as $recorded) {
            if ($recorded['operation'] === $operation) {
                return $recorded['arguments'];
            }
        }
        throw new \RuntimeException("No operation recorded for {$operation}");
    }
}

final class ContributionAuthorStub
{
    public function getLocalizedFamilyName(): string
    {
        return 'Lovelace';
    }
    public function getLocalizedGivenName(): string
    {
        return 'Ada';
    }
    public function getFullName(bool $preferred): string
    {
        return 'Ada Lovelace';
    }
    public function getOrcid(): string
    {
        return 'https://orcid.org/0000-0000-0000-0001';
    }
    public function getUrl(): ?string
    {
        return null;
    }
    public function getAffiliations(): array
    {
        return [new ContributionAffiliationStub()];
    }
    public function getData(string $key)
    {
        return match ($key) {
            'locale' => 'en',
            'biography' => ['en' => '<p>Mathematician</p>'],
            default => null,
        };
    }
}

final class ContributionAffiliationStub
{
    public function getRor(): string
    {
        return 'https://ror.org/12345';
    }
}

final class RecordingChapterGateway implements ChapterMetadataGateway
{
    public array $deleted = [];
    public function __construct(private string $createdId)
    {
    }
    public function create(array $metadata): string
    {
        return $this->createdId;
    }
    public function delete(WorkId $workId): void
    {
        $this->deleted[] = $workId->toString();
    }
}

final class FixedWorkMapper implements WorkMetadataMapper
{
    public function fromPublication(object $publication): array
    {
        return ['workType' => 'BOOK_CHAPTER'];
    }
}

final class SuccessfulSynchronizer implements DomainSynchronizer
{
    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        return new SynchronizationResult();
    }
}

final class RecordingFrontcoverLocalGateway implements FrontcoverLocalGateway
{
    public bool $disabled = false;
    public ?string $savedUrl = null;

    public function __construct(
        private string $path,
        private string $extension = 'jpg',
        private string $mimeType = 'image/jpeg'
    ) {
    }

    public function isEnabled(object $publication): bool
    {
        return true;
    }
    public function clearUploadData(object $publication): void
    {
    }
    public function resolveFile(object $publication): ?array
    {
        return ['path' => $this->path, 'extension' => $this->extension,
            'mimeType' => $this->mimeType, 'sha256' => hash('sha256', 'jpeg-content'),
            'uploadedSha256' => null];
    }
    public function disable(object $publication): void
    {
        $this->disabled = true;
    }
    public function saveUploadData(object $publication, string $sha256, string $cdnUrl): void
    {
        $this->savedUrl = $cdnUrl;
    }
}

final class RecordingHttpClient
{
    public ?string $method = null;
    public function request(string $method, string $url, array $options): void
    {
        $this->method = $method;
    }
}

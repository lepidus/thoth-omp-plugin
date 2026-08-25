<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth\Synchronization;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\FailureReporting\ThothErrorTranslator;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Abstracts\ThothAbstractMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Chapters\ThothChapterMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Languages\ThothLanguageMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Locations\ThothLocationMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Publications\ThothPublicationMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\References\ThothReferenceMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Subjects\ThothSubjectMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Titles\ThothTitleMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Works\ThothWorkMetadataGateway;
use PHPUnit\Framework\TestCase;
use ThothApi\GraphQL\Enums\MarkupFormat;
use ThothApi\GraphQL\Inputs\NewAbstract;
use ThothApi\GraphQL\Inputs\NewLanguage;
use ThothApi\GraphQL\Inputs\NewLocation;
use ThothApi\GraphQL\Inputs\NewPublication;
use ThothApi\GraphQL\Inputs\NewReference;
use ThothApi\GraphQL\Inputs\NewSubject;
use ThothApi\GraphQL\Inputs\NewTitle;
use ThothApi\GraphQL\Inputs\NewWork;
use ThothApi\GraphQL\Inputs\PatchAbstract;
use ThothApi\GraphQL\Inputs\PatchLanguage;
use ThothApi\GraphQL\Inputs\PatchLocation;
use ThothApi\GraphQL\Inputs\PatchPublication;
use ThothApi\GraphQL\Inputs\PatchReference;
use ThothApi\GraphQL\Inputs\PatchSubject;
use ThothApi\GraphQL\Inputs\PatchTitle;
use ThothApi\GraphQL\Inputs\PatchWork;
use ThothApi\GraphQL\Schemas\Publication;
use ThothApi\GraphQL\Schemas\Work;

final class ThothMetadataGatewaysTest extends TestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testAbstractGatewayUsesLongSnapshotAndTypedMutations(): void
    {
        $client = new RecordingMetadataClient();
        $client->responses['work'] = new Work(['abstracts' => [
            ['abstractId' => 'long-id', 'localeCode' => 'EN', 'content' => '<p>Long</p>',
                'abstractType' => 'LONG', 'canonical' => true, 'ignored' => 'value'],
            ['abstractId' => 'short-id', 'abstractType' => 'SHORT'],
        ]]);
        $gateway = new ThothAbstractMetadataGateway($this->remote($client));
        $metadata = ['localeCode' => 'EN', 'content' => '<p>Long</p>', 'abstractType' => 'LONG',
            'canonical' => true];

        $this->assertSame([[
            'abstractId' => 'long-id', 'localeCode' => 'EN', 'content' => '<p>Long</p>',
            'abstractType' => 'LONG', 'canonical' => true,
        ]], $gateway->snapshot($this->workId()));
        $gateway->create($this->workId(), $metadata);
        $gateway->update($this->workId(), 'long-id', $metadata);
        $gateway->delete('obsolete-id');

        $this->assertSame(MarkupFormat::HTML, $client->operations[1]['arguments'][0]);
        $this->assertInstanceOf(NewAbstract::class, $client->operations[1]['arguments'][1]);
        $this->assertSame(self::WORK_ID, $client->operations[1]['arguments'][1]->getWorkId());
        $this->assertInstanceOf(PatchAbstract::class, $client->operations[2]['arguments'][1]);
        $this->assertSame('long-id', $client->operations[2]['arguments'][1]->getAbstractId());
        $this->assertSame(['obsolete-id'], $client->operations[3]['arguments']);
    }

    public function testTitleGatewayUsesMinimalSnapshotAndTypedMutations(): void
    {
        $client = new RecordingMetadataClient();
        $client->responses['work'] = new Work(['titles' => [[
            'titleId' => 'title-id', 'localeCode' => 'EN', 'fullTitle' => 'Book: Subtitle',
            'title' => 'Book', 'subtitle' => 'Subtitle', 'canonical' => true, 'ignored' => 'value',
        ]]]);
        $gateway = new ThothTitleMetadataGateway($this->remote($client));
        $metadata = ['localeCode' => 'EN', 'fullTitle' => 'Book: Subtitle', 'title' => 'Book',
            'subtitle' => 'Subtitle', 'canonical' => true];

        $this->assertSame([[
            'titleId' => 'title-id', 'localeCode' => 'EN', 'fullTitle' => 'Book: Subtitle',
            'title' => 'Book', 'subtitle' => 'Subtitle', 'canonical' => true,
        ]], $gateway->snapshot($this->workId()));
        $gateway->create($this->workId(), $metadata);
        $gateway->update($this->workId(), 'title-id', $metadata);
        $gateway->delete('obsolete-id');

        $this->assertSame(MarkupFormat::PLAIN_TEXT, $client->operations[1]['arguments'][0]);
        $this->assertInstanceOf(NewTitle::class, $client->operations[1]['arguments'][1]);
        $this->assertInstanceOf(PatchTitle::class, $client->operations[2]['arguments'][1]);
        $this->assertSame('title-id', $client->operations[2]['arguments'][1]->getTitleId());
    }

    public function testLanguageSubjectAndReferenceGatewaysUseTypedRemoteOperations(): void
    {
        $client = new RecordingMetadataClient();
        $client->responses['work'] = new Work([
            'languages' => [['languageId' => 'language-id', 'languageCode' => 'POR',
                'languageRelation' => 'ORIGINAL']],
            'subjects' => [['subjectId' => 'subject-id', 'subjectType' => 'THEMA',
                'subjectCode' => 'MFGV', 'subjectOrdinal' => 1]],
            'references' => [['referenceId' => 'reference-id', 'referenceOrdinal' => 1,
                'doi' => 'https://doi.org/10.1234/example', 'unstructuredCitation' => 'Reference']],
        ]);
        $language = new ThothLanguageMetadataGateway($this->remote($client));
        $subject = new ThothSubjectMetadataGateway($this->remote($client));
        $reference = new ThothReferenceMetadataGateway($this->remote($client));

        $this->assertSame('language-id', $language->snapshot($this->workId())[0]['languageId']);
        $language->create($this->workId(), ['languageCode' => 'POR', 'languageRelation' => 'ORIGINAL']);
        $language->update($this->workId(), 'language-id', ['languageCode' => 'ENG',
            'languageRelation' => 'ORIGINAL']);
        $this->assertSame('subject-id', $subject->snapshot($this->workId())[0]['subjectId']);
        $subject->create($this->workId(), ['subjectType' => 'KEYWORD', 'subjectCode' => 'Open',
            'subjectOrdinal' => 1]);
        $subject->update($this->workId(), 'subject-id', ['subjectType' => 'THEMA',
            'subjectCode' => 'MFGV', 'subjectOrdinal' => 2]);
        $subject->delete('subject-obsolete');
        $this->assertSame('reference-id', $reference->snapshot($this->workId())[0]['referenceId']);
        $reference->create($this->workId(), ['referenceOrdinal' => 1, 'doi' => '10.1234/example']);
        $reference->update($this->workId(), 'reference-id', ['referenceOrdinal' => 2]);
        $reference->delete('reference-obsolete');

        $this->assertInstanceOf(NewLanguage::class, $client->inputFor('createLanguage'));
        $this->assertInstanceOf(PatchLanguage::class, $client->inputFor('updateLanguage'));
        $this->assertInstanceOf(NewSubject::class, $client->inputFor('createSubject'));
        $this->assertInstanceOf(PatchSubject::class, $client->inputFor('updateSubject'));
        $this->assertInstanceOf(NewReference::class, $client->inputFor('createReference'));
        $this->assertSame('https://doi.org/10.1234/example', $client->inputFor('createReference')->getDoi());
        $this->assertInstanceOf(PatchReference::class, $client->inputFor('updateReference'));
    }

    public function testPublicationAndLocationGatewaysKeepLocationsOutsidePublicationMutations(): void
    {
        $client = new RecordingMetadataClient();
        $client->responses['work'] = new Work(['workStatus' => 'FORTHCOMING', 'publications' => [[
            'publicationId' => 'publication-id', 'publicationType' => 'PDF',
            'locations' => [['locationId' => 'location-id']],
        ]]]);
        $client->responses['createPublication'] = new Publication(['publicationId' => 'new-publication-id']);
        $publication = new ThothPublicationMetadataGateway($this->remote($client));
        $location = new ThothLocationMetadataGateway($this->remote($client));
        $metadata = ['publicationType' => 'PDF', 'isbn' => null,
            'locations' => [['fullTextUrl' => 'https://example.test/book.pdf']]];

        $this->assertSame('FORTHCOMING', $publication->snapshot($this->workId())['workStatus']);
        $this->assertSame('new-publication-id', $publication->create($this->workId(), $metadata));
        $publication->update($this->workId(), 'publication-id', $metadata, false);
        $publication->update($this->workId(), 'publication-id', $metadata, true);
        $publication->delete('obsolete-publication');
        $location->create('publication-id', ['fullTextUrl' => 'https://example.test/book.pdf']);
        $location->update('publication-id', 'location-id', ['canonical' => true]);
        $location->delete('obsolete-location');

        $this->assertSame(1, $client->count('updatePublication'));
        $this->assertInstanceOf(NewPublication::class, $client->inputFor('createPublication'));
        $this->assertArrayNotHasKey('locations', $client->inputFor('createPublication')->getAllData());
        $this->assertInstanceOf(PatchPublication::class, $client->inputFor('updatePublication'));
        $this->assertInstanceOf(NewLocation::class, $client->inputFor('createLocation'));
        $this->assertInstanceOf(PatchLocation::class, $client->inputFor('updateLocation'));
    }

    public function testWorkAndChapterGatewaysUsePersistentWorkIds(): void
    {
        $client = new RecordingMetadataClient();
        $client->responses['work'] = new Work([
            'workId' => self::WORK_ID, 'workStatus' => 'ACTIVE',
            'doi' => 'https://doi.org/10.1234/book', 'fullTitle' => 'Ignored title',
        ]);
        $client->responses['createWork'] = new Work(['workId' => 'f5ef15f6-c1ad-4876-862d-77fcc69ee2d7']);
        $work = new ThothWorkMetadataGateway($this->remote($client));
        $chapter = new ThothChapterMetadataGateway($this->remote($client));

        $this->assertSame([
            'workId' => self::WORK_ID,
            'workStatus' => 'ACTIVE',
            'doi' => 'https://doi.org/10.1234/book',
        ], $work->snapshot($this->workId()));
        $work->update($this->workId(), ['doi' => 'https://doi.org/10.1234/new']);
        $this->assertSame(
            'f5ef15f6-c1ad-4876-862d-77fcc69ee2d7',
            $chapter->create(['workType' => 'BOOK_CHAPTER', 'workStatus' => 'FORTHCOMING'])
        );
        $chapter->delete($this->workId());

        $this->assertInstanceOf(PatchWork::class, $client->inputFor('updateWork'));
        $this->assertSame(self::WORK_ID, $client->inputFor('updateWork')->getWorkId());
        $this->assertInstanceOf(NewWork::class, $client->inputFor('createWork'));
        $this->assertSame([self::WORK_ID], $client->argumentsFor('deleteWork'));
    }

    private function workId(): WorkId
    {
        return new WorkId(self::WORK_ID);
    }

    private function remote(object $client): ThothRemoteGateway
    {
        return new ThothRemoteGateway($client, new ThothErrorTranslator());
    }
}

final class RecordingMetadataClient
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
            if ($recorded['operation'] !== $operation) {
                continue;
            }
            foreach ($recorded['arguments'] as $argument) {
                if (is_object($argument) && method_exists($argument, 'getAllData')) {
                    return $argument;
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

    public function count(string $operation): int
    {
        return count(array_filter(
            $this->operations,
            fn (array $recorded): bool => $recorded['operation'] === $operation
        ));
    }
}

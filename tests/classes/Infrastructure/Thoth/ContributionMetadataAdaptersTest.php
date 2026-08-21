<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Contracts\ContributionAuthorReader;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\factories\ThothContributionFactory;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyContributionMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\ThothContributionMetadataMapper;
use PKP\tests\PKPTestCase;
use ThothApi\GraphQL\Inputs\PatchContribution;

final class ContributionMetadataAdaptersTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testMapperKeepsOnlyDesiredMetadataAndRequestLocalCollaborators(): void
    {
        $author = new class () {
            public function getOrcid(): string
            {
                return 'https://orcid.org/0000-0001-2345-6789';
            }
        };
        $factory = new class () extends ThothContributionFactory {
            public function createFromAuthor($author, $sequence, $primaryContactId = null)
            {
                return new PatchContribution([
                    'contributionId' => 'must-not-cross-the-mapper',
                    'contributionType' => 'AUTHOR',
                    'mainContribution' => true,
                    'contributionOrdinal' => $sequence + 1,
                    'firstName' => 'Jane',
                    'lastName' => 'Doe',
                    'fullName' => 'Jane Doe',
                ]);
            }
        };
        $reader = new class ($author) implements ContributionAuthorReader {
            private $author;

            public function __construct($author)
            {
                $this->author = $author;
            }

            public function forPublication(object $publication, ?int $primaryContactId): array
            {
                return [$this->author];
            }

            public function forChapter(object $chapter): array
            {
                return [];
            }
        };
        $publication = new class () {
            public function getData($key)
            {
                return $key === 'primaryContactId' ? 42 : null;
            }
        };

        $mapped = (new ThothContributionMetadataMapper($reader, $factory))->fromPublication(
            $publication,
            $this->workId()
        );

        $this->assertSame($author, $mapped[0]['author']);
        $this->assertSame(42, $mapped[0]['primaryContactId']);
        $this->assertSame('https://orcid.org/0000-0001-2345-6789', $mapped[0]['orcid']);
        $this->assertSame('AUTHOR', $mapped[0]['contributionType']);
        $this->assertArrayNotHasKey('contributionId', $mapped[0]);
    }

    public function testGatewayUsesRemoteIdsOnlyForTheCurrentMutations(): void
    {
        $repository = new class () {
            public array $deleted = [];

            public function getByWorkId(string $workId): array
            {
                return [['contributionId' => 'contribution-id', 'workId' => $workId]];
            }

            public function new(array $metadata): PatchContribution
            {
                return new PatchContribution($metadata);
            }

            public function delete(string $contributionId): void
            {
                $this->deleted[] = $contributionId;
            }
        };
        $service = new class ($repository) {
            public $repository;
            public array $registered = [];
            public array $updated = [];

            public function __construct($repository)
            {
                $this->repository = $repository;
            }

            public function register($author, $sequence, $workId, $primaryContactId): void
            {
                $this->registered = [$author, $sequence, $workId, $primaryContactId];
            }

            public function updateContribution($author, $contribution, array $remote, bool $changed): void
            {
                $this->updated = [$author, $contribution, $remote, $changed];
            }
        };
        $author = new \stdClass();
        $metadata = [
            'author' => $author,
            'primaryContactId' => 42,
            'contributionType' => 'AUTHOR',
            'mainContribution' => true,
            'contributionOrdinal' => 2,
            'fullName' => 'Jane Doe',
            'orcid' => null,
        ];
        $remote = ['contributionId' => 'contribution-id'];
        $gateway = new LegacyContributionMetadataGateway($service);

        $this->assertSame('contribution-id', $gateway->snapshot($this->workId())[0]['contributionId']);
        $gateway->create($this->workId(), $metadata);
        $gateway->update($this->workId(), 'contribution-id', $metadata, $remote, false);
        $gateway->delete('obsolete-contribution-id');

        $this->assertSame([$author, 1, self::WORK_ID, 42], $service->registered);
        $this->assertSame($author, $service->updated[0]);
        $this->assertSame('contribution-id', $service->updated[1]->getContributionId());
        $this->assertSame(self::WORK_ID, $service->updated[1]->getWorkId());
        $this->assertSame($remote, $service->updated[2]);
        $this->assertFalse($service->updated[3]);
        $this->assertSame(['obsolete-contribution-id'], $repository->deleted);
    }

    private function workId(): WorkId
    {
        return new WorkId(self::WORK_ID);
    }
}

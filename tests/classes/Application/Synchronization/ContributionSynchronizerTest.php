<?php

use PHPUnit\Framework\TestCase;

final class ContributionSynchronizerTest extends TestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testItMatchesByNormalizedOrcidWithoutUpdatingEquivalentContributionMetadata(): void
    {
        $publication = new stdClass();
        $author = new stdClass();
        $desired = $this->desiredContribution($author, [
            'orcid' => 'https://orcid.org/0000-0001-2345-6789',
        ]);
        $remote = $this->remoteContribution([
            'contributor' => [
                'contributorId' => 'contributor-id',
                'orcid' => '0000-0001-2345-6789',
                'fullName' => 'Jane Doe',
            ],
        ]);

        $mapper = $this->createMock(ContributionMetadataMapper::class);
        $mapper->expects($this->once())->method('fromPublication')
            ->with($publication, $this->workId())
            ->willReturn([$desired]);
        $gateway = $this->createMock(ContributionMetadataGateway::class);
        $gateway->expects($this->once())->method('snapshot')->with($this->workId())->willReturn([$remote]);
        $gateway->expects($this->once())->method('update')
            ->with($this->workId(), 'contribution-id', $desired, $remote, false);
        $gateway->expects($this->never())->method('create');
        $gateway->expects($this->never())->method('delete');

        (new ContributionSynchronizer($gateway, $mapper))->synchronize($publication, $this->workId());
    }

    public function testItFallsBackToTypeAndOrdinalWhenAnAuthorWasRenamed(): void
    {
        $publication = new stdClass();
        $desired = $this->desiredContribution(new stdClass(), ['fullName' => 'Juliana Castanheiras']);
        $remote = $this->remoteContribution([
            'fullName' => 'Iris Castanheiras',
            'contributor' => ['fullName' => 'Iris Castanheiras', 'orcid' => null],
        ]);

        $mapper = $this->createConfiguredMock(ContributionMetadataMapper::class, [
            'fromPublication' => [$desired],
        ]);
        $gateway = $this->createMock(ContributionMetadataGateway::class);
        $gateway->method('snapshot')->willReturn([$remote]);
        $gateway->expects($this->once())->method('update')
            ->with($this->workId(), 'contribution-id', $desired, $remote, true);
        $gateway->expects($this->never())->method('create');
        $gateway->expects($this->never())->method('delete');

        (new ContributionSynchronizer($gateway, $mapper))->synchronize($publication, $this->workId());
    }

    public function testItCreatesAndDeletesUnmatchedContributions(): void
    {
        $desired = $this->desiredContribution(new stdClass());
        $remote = $this->remoteContribution(['contributionType' => 'EDITOR']);
        $mapper = $this->createConfiguredMock(ContributionMetadataMapper::class, [
            'fromPublication' => [$desired],
        ]);
        $gateway = $this->createMock(ContributionMetadataGateway::class);
        $gateway->method('snapshot')->willReturn([$remote]);
        $gateway->expects($this->once())->method('create')->with($this->workId(), $desired);
        $gateway->expects($this->never())->method('update');
        $gateway->expects($this->once())->method('delete')->with('contribution-id');

        (new ContributionSynchronizer($gateway, $mapper))->synchronize(new stdClass(), $this->workId());
    }

    public function testItRejectsAmbiguousRemoteIdentityBeforeMutating(): void
    {
        $desired = $this->desiredContribution(new stdClass(), [
            'orcid' => '0000-0001-2345-6789',
        ]);
        $remote = $this->remoteContribution([
            'contributor' => ['orcid' => 'https://orcid.org/0000-0001-2345-6789'],
        ]);
        $duplicate = $remote;
        $duplicate['contributionId'] = 'duplicate-contribution-id';
        $duplicate['contributionOrdinal'] = 2;

        $mapper = $this->createConfiguredMock(ContributionMetadataMapper::class, [
            'fromPublication' => [$desired],
        ]);
        $gateway = $this->createMock(ContributionMetadataGateway::class);
        $gateway->method('snapshot')->willReturn([$remote, $duplicate]);
        $gateway->expects($this->never())->method('create');
        $gateway->expects($this->never())->method('update');
        $gateway->expects($this->never())->method('delete');

        $this->expectException(InvalidRemoteMetadata::class);

        (new ContributionSynchronizer($gateway, $mapper))->synchronize(new stdClass(), $this->workId());
    }

    public function testItRejectsIncompleteRemoteContributionBeforeMutating(): void
    {
        $remote = $this->remoteContribution();
        unset(
            $remote['contributionType'],
            $remote['contributionOrdinal'],
            $remote['fullName'],
            $remote['contributor']
        );
        $mapper = $this->createConfiguredMock(ContributionMetadataMapper::class, [
            'fromPublication' => [],
        ]);
        $gateway = $this->createMock(ContributionMetadataGateway::class);
        $gateway->method('snapshot')->willReturn([$remote]);
        $gateway->expects($this->never())->method('create');
        $gateway->expects($this->never())->method('update');
        $gateway->expects($this->never())->method('delete');

        $this->expectException(InvalidRemoteMetadata::class);

        (new ContributionSynchronizer($gateway, $mapper))->synchronize(new stdClass(), $this->workId());
    }

    private function desiredContribution(object $author, array $overrides = []): array
    {
        return array_merge([
            'author' => $author,
            'primaryContactId' => 1,
            'contributionType' => 'AUTHOR',
            'mainContribution' => true,
            'contributionOrdinal' => 1,
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'fullName' => 'Jane Doe',
            'orcid' => null,
        ], $overrides);
    }

    private function remoteContribution(array $overrides = []): array
    {
        return array_merge([
            'contributionId' => 'contribution-id',
            'contributorId' => 'contributor-id',
            'contributionType' => 'AUTHOR',
            'mainContribution' => true,
            'contributionOrdinal' => 1,
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'fullName' => 'Jane Doe',
            'contributor' => ['orcid' => null, 'fullName' => 'Jane Doe'],
            'biographies' => [],
            'affiliations' => [],
        ], $overrides);
    }

    private function workId(): WorkId
    {
        return new WorkId(self::WORK_ID);
    }
}

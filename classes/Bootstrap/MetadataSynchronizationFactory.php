<?php

namespace APP\plugins\generic\thoth\classes\Bootstrap;

use APP\plugins\generic\thoth\classes\Application\Synchronization\AbstractSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\ChapterSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\ContributionSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\FrontcoverSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\LanguageSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\ContributionAuthorReader;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\FrontcoverLocalGateway;
use APP\plugins\generic\thoth\classes\Application\Synchronization\PublicationSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\ReferenceSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SubjectSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeLocations;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeMetadata;
use APP\plugins\generic\thoth\classes\Application\Synchronization\TitleSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\WorkRelationSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\WorkSynchronizer;
use APP\plugins\generic\thoth\classes\Domain\Registration\BookRegistrationPolicy;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Abstracts\PkpAbstractMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Chapters\PkpChapterAbstractMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Chapters\PkpChapterPublicationMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Chapters\PkpChapterTitleMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Chapters\PkpChapterWorkMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Contributions\PkpChapterContributionMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Contributions\PkpContributionMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Languages\PkpLanguageMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Publications\PkpPublicationMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Publications\PkpPublicationMetadataReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\References\PkpReferenceMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Relations\PkpWorkRelationMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Subjects\PkpSubjectMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Titles\PkpTitleMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Works\PkpWorkMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Works\PkpWorkMetadataReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets\ThothPresignedFileUploader;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Abstracts\ThothAbstractMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Chapters\ThothChapterMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Contributions\ThothContributionMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Frontcover\ThothFrontcoverGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Languages\ThothLanguageMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Locations\ThothLocationMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Publications\ThothPublicationMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\References\ThothReferenceMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Relations\ThothWorkRelationMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Subjects\ThothSubjectMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\ThothBookMetadataUpdater;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Titles\ThothTitleMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Works\ThothWorkMetadataGateway;

final class MetadataSynchronizationFactory
{
    private ?SynchronizeMetadata $synchronizer = null;
    private ?ThothBookMetadataUpdater $metadataUpdater = null;
    private ?PkpWorkMetadataMapper $workMapper = null;

    public function __construct(
        private readonly ThothRemoteGateway $remote,
        private readonly PkpWorkMetadataReader $workMetadataReader,
        private readonly PkpPublicationMetadataReader $publicationMetadataReader,
        private readonly ContributionAuthorReader $authors,
        private readonly object $citationDao,
        private readonly object $chapterDao,
        private readonly ThothPresignedFileUploader $presignedUploader,
        private readonly FrontcoverLocalGateway $frontcoverLocal,
        private readonly BookRegistrationPolicy $registrationPolicy = new BookRegistrationPolicy()
    ) {
    }

    public function synchronizer(): SynchronizeMetadata
    {
        if ($this->synchronizer !== null) {
            return $this->synchronizer;
        }

        [$work, $title, $abstract, $frontcover] = $this->bookCoreSynchronizers();
        $chapterWorkMapper = new PkpChapterWorkMetadataMapper($this->workMetadataReader);
        $chapter = new ChapterSynchronizer(
            new ThothChapterMetadataGateway($this->remote),
            $chapterWorkMapper,
            new WorkSynchronizer(
                new ThothWorkMetadataGateway($this->remote),
                $chapterWorkMapper,
                $this->registrationPolicy
            ),
            [
                new TitleSynchronizer(
                    new ThothTitleMetadataGateway($this->remote),
                    new PkpChapterTitleMetadataMapper()
                ),
                new AbstractSynchronizer(
                    new ThothAbstractMetadataGateway($this->remote),
                    new PkpChapterAbstractMetadataMapper()
                ),
                new ContributionSynchronizer(
                    new ThothContributionMetadataGateway($this->remote),
                    new PkpChapterContributionMetadataMapper($this->authors)
                ),
                new PublicationSynchronizer(
                    new ThothPublicationMetadataGateway($this->remote),
                    new PkpChapterPublicationMetadataMapper($this->publicationMetadataReader),
                    new SynchronizeLocations(new ThothLocationMetadataGateway($this->remote))
                ),
            ],
            $this->registrationPolicy
        );

        return $this->synchronizer = new SynchronizeMetadata(
            $work,
            $title,
            $abstract,
            $frontcover,
            new ContributionSynchronizer(
                new ThothContributionMetadataGateway($this->remote),
                new PkpContributionMetadataMapper($this->authors)
            ),
            new PublicationSynchronizer(
                new ThothPublicationMetadataGateway($this->remote),
                new PkpPublicationMetadataMapper($this->publicationMetadataReader),
                new SynchronizeLocations(new ThothLocationMetadataGateway($this->remote))
            ),
            new LanguageSynchronizer(
                new ThothLanguageMetadataGateway($this->remote),
                new PkpLanguageMetadataMapper()
            ),
            new SubjectSynchronizer(
                new ThothSubjectMetadataGateway($this->remote),
                new PkpSubjectMetadataMapper()
            ),
            new ReferenceSynchronizer(
                new ThothReferenceMetadataGateway($this->remote),
                new PkpReferenceMetadataMapper($this->citationDao)
            ),
            new WorkRelationSynchronizer(
                new ThothWorkRelationMetadataGateway($this->remote, $chapter),
                new PkpWorkRelationMetadataMapper($this->chapterDao, $chapterWorkMapper)
            )
        );
    }

    public function bookMetadataUpdater(): ThothBookMetadataUpdater
    {
        if ($this->metadataUpdater === null) {
            [$work, $title, $abstract, $frontcover] = $this->bookCoreSynchronizers();
            $this->metadataUpdater = new ThothBookMetadataUpdater($work, $title, $abstract, $frontcover);
        }

        return $this->metadataUpdater;
    }

    public function workMetadataMapper(): PkpWorkMetadataMapper
    {
        return $this->workMapper ??= new PkpWorkMetadataMapper($this->workMetadataReader);
    }

    private function bookCoreSynchronizers(): array
    {
        return [
            new WorkSynchronizer(
                new ThothWorkMetadataGateway($this->remote),
                $this->workMetadataMapper(),
                $this->registrationPolicy
            ),
            new TitleSynchronizer(new ThothTitleMetadataGateway($this->remote), new PkpTitleMetadataMapper()),
            new AbstractSynchronizer(
                new ThothAbstractMetadataGateway($this->remote),
                new PkpAbstractMetadataMapper()
            ),
            new FrontcoverSynchronizer(new ThothFrontcoverGateway(
                $this->remote,
                $this->presignedUploader,
                $this->frontcoverLocal
            )),
        ];
    }
}

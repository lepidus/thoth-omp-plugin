<?php


final class MetadataSynchronizationFactory
{
    private ?SynchronizeMetadata $synchronizer = null;
    private ?ThothBookMetadataUpdater $metadataUpdater = null;
    private ?PkpWorkMetadataMapper $workMapper = null;
    private BookRegistrationPolicy $registrationPolicy;

    private ThothRemoteGateway $remote;
    private PkpWorkMetadataReader $workMetadataReader;
    private PkpPublicationMetadataReader $publicationMetadataReader;
    private ContributionAuthorReader $authors;
    private object $citationDao;
    private object $chapterDao;
    private ThothPresignedFileUploader $presignedUploader;
    private FrontcoverLocalGateway $frontcoverLocal;
    public function __construct(
        ThothRemoteGateway $remote,
        PkpWorkMetadataReader $workMetadataReader,
        PkpPublicationMetadataReader $publicationMetadataReader,
        ContributionAuthorReader $authors,
        object $citationDao,
        object $chapterDao,
        ThothPresignedFileUploader $presignedUploader,
        FrontcoverLocalGateway $frontcoverLocal,
        ?BookRegistrationPolicy $registrationPolicy = null
    ) {
        $this->remote = $remote;
        $this->workMetadataReader = $workMetadataReader;
        $this->publicationMetadataReader = $publicationMetadataReader;
        $this->authors = $authors;
        $this->citationDao = $citationDao;
        $this->chapterDao = $chapterDao;
        $this->presignedUploader = $presignedUploader;
        $this->frontcoverLocal = $frontcoverLocal;
        $this->registrationPolicy = $registrationPolicy ?? new BookRegistrationPolicy();
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

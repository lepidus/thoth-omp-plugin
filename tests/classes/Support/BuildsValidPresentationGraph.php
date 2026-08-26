<?php

import('lib.pkp.classes.core.PKPRequest');

trait BuildsValidPresentationGraph
{
    protected function buildEndpoint(
        ?SubmissionReader $submissionReader = null,
        ?PublicationReader $publicationReader = null,
        ?PKPRequest $request = null
    ): ThothEndpoint {
        $workGateway = $this->createMock(WorkGateway::class);
        $submissionLinks = $this->createMock(SubmissionLinkRepository::class);
        $notifications = $this->createMock(NotificationPublisher::class);
        $failureReporter = new ExternalFailureReporter(
            $notifications,
            $this->createMock(PluginLogger::class)
        );
        $getWorkStatus = new GetWorkStatus($workGateway);

        return new ThothEndpoint(
            new GetWorkStatusController($getWorkStatus),
            new RegisterBookController(
                new RegisterBook(
                    $this->createMock(BookRegistrar::class),
                    $submissionLinks
                ),
                new BookRegistrationPolicy(),
                $this->createMock(RegistrationMetadataValidator::class),
                $this->createMock(SubmissionReader::class),
                $this->createMock(SubmissionResponseMapper::class),
                $getWorkStatus,
                $notifications,
                $failureReporter
            ),
            new SynchronizeMetadataController(
                new SynchronizeMetadata(),
                $notifications,
                $failureReporter
            ),
            new UnlinkWorkController(new UnlinkWork($workGateway, $submissionLinks)),
            new UploadFeatureVideoController(new UploadFeatureVideo(
                $this->createMock(TemporaryVideoFileRepository::class),
                $this->createMock(FeatureVideoUploader::class),
                $this->createMock(FeatureVideoCache::class)
            )),
            $submissionReader ?? $this->createMock(SubmissionReader::class),
            $publicationReader ?? $this->createMock(PublicationReader::class),
            $this->createMock(PublisherAccessGateway::class),
            new GetFeatureVideo($this->createMock(FeatureVideoReader::class)),
            $request ?? $this->createMock(PKPRequest::class),
            static fn (): object => new stdClass()
        );
    }

    /** @return array{router: ThothPageHandler, register: RegisterHandler} */
    protected function buildPageHandler(GenericPlugin $plugin): array
    {
        $publisherAccess = $this->createMock(PublisherAccessGateway::class);
        $catalogFiles = $this->createMock(CatalogPublicationFilesProvider::class);
        $register = new RegisterHandler(
            $plugin,
            new stdClass(),
            $this->createMock(RegistrationMetadataValidator::class),
            $publisherAccess,
            static fn (): object => new stdClass()
        );
        $catalog = new ThothCatalogFilesHandler($catalogFiles);
        $upload = new UploadThothFileHandler(
            $plugin,
            new stdClass(),
            $this->createMock(PublicationFileFormReader::class),
            $this->createMock(TemporaryUploadReceiver::class),
            $catalogFiles,
            new UploadPublicationFile(
                $this->createMock(TemporaryPublicationFileRepository::class),
                $this->createMock(PublicationFileUploader::class),
                $this->createMock(CatalogFileCache::class)
            ),
            $this->createMock(NotificationPublisher::class),
            $publisherAccess,
            static fn (): object => new stdClass()
        );
        $index = new ThothHandler(
            $plugin,
            $publisherAccess,
            $this->createMock(SubmissionListProvider::class),
            new stdClass(),
            static fn (): object => new stdClass()
        );

        return [
            'router' => new ThothPageHandler($plugin, $register, $catalog, $upload, $index),
            'register' => $register,
        ];
    }

    protected function buildHookRegistrant(GenericPlugin $plugin): HookRegistrant
    {
        $notifications = $this->createMock(NotificationPublisher::class);
        $failureReporter = new ExternalFailureReporter(
            $notifications,
            $this->createMock(PluginLogger::class)
        );
        $publisherAccess = $this->createMock(PublisherAccessGateway::class);
        $metadataValidator = $this->createMock(RegistrationMetadataValidator::class);
        $submissionLinks = $this->createMock(SubmissionLinkRepository::class);
        $catalogFiles = $this->createMock(CatalogPublicationFilesProvider::class);
        $pageHandler = $this->buildPageHandler($plugin)['router'];

        return new HookRegistrant(
            $plugin,
            new ThothSchema(),
            new PublishFormConfig(
                $this->createMock(SubmissionReader::class),
                $metadataValidator,
                $publisherAccess
            ),
            new CatalogEntryFormConfig(
                $this->createMock(PublicationReader::class),
                $publisherAccess
            ),
            new ContributorFormConfig(),
            new PublicationFormatFormHandler(new PublicationFormatTemplateFilter($plugin)),
            new PublicationPublishListener(
                new RegisterBook($this->createMock(BookRegistrar::class), $submissionLinks),
                new stdClass(),
                $notifications,
                new BookRegistrationPolicy(),
                $metadataValidator,
                $failureReporter
            ),
            new PublicationEditListener(
                new UpdatePublicationAfterEdit(
                    $submissionLinks,
                    $this->createMock(BookMetadataUpdater::class)
                ),
                $notifications,
                $failureReporter
            ),
            $this->buildEndpoint(),
            new ThothCatalogFilesTemplateFilter(),
            new ThothFrontcoverTemplateFilter(),
            new ThothFeatureVideoTemplateFilter(
                new GetFeatureVideo($this->createMock(FeatureVideoReader::class))
            ),
            new ThothSectionTemplateFilter(),
            new ThothNotification(),
            new ThothMenuHandler(new stdClass()),
            $pageHandler,
            new PublicationFormatGridModifier(new stdClass()),
            $catalogFiles,
            new stdClass()
        );
    }
}

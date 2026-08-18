<?php

/**
 * @file plugins/generic/thoth/ThothPlugin.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothPlugin
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Plugin for integration with Thoth for communication and synchronization of book data between the two platforms
 */

namespace APP\plugins\generic\thoth;

require_once __DIR__ . '/vendor/autoload.php';

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\thoth\classes\Application\Catalog\GetCatalogFiles;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadPublicationFile;
use APP\plugins\generic\thoth\classes\Application\Synchronization\AbstractSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\ChapterSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\ContributionSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\FrontcoverSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\LanguageSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\PublicationSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\ReferenceSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SubjectSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeLocations;
use APP\plugins\generic\thoth\classes\Application\Synchronization\TitleSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\WorkRelationSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\WorkSynchronizer;
use APP\plugins\generic\thoth\classes\Bootstrap\ThothCompositionRoot;
use APP\plugins\generic\thoth\classes\container\ThothContainer;
use APP\plugins\generic\thoth\classes\facades\ThothRepository;
use APP\plugins\generic\thoth\classes\facades\ThothService;
use APP\plugins\generic\thoth\classes\hooks\HookRegistrant;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyAbstractMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyAbstractMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyChapterAbstractMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyChapterContributionMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyChapterMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyChapterPublicationMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyChapterTitleMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyChapterWorkMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyContributionMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyContributionMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyFrontcoverGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyLanguageMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyLanguageMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyLocationMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyPublicationMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyPublicationMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyReferenceMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyReferenceMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacySubjectMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacySubjectMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyTitleMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyTitleMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyWorkMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyWorkMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyWorkRelationMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyWorkRelationMetadataMapper;
use APP\plugins\generic\thoth\classes\listeners\PublicationPublishListener;
use APP\plugins\generic\thoth\classes\notification\ThothNotification;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;
use APP\plugins\generic\thoth\classes\Presentation\Api\RegisterBookController;
use APP\plugins\generic\thoth\classes\Presentation\Api\SynchronizeMetadataController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UnlinkWorkController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UploadFeatureVideoController;
use APP\plugins\generic\thoth\classes\services\ThothSubjectClassifier;
use APP\plugins\generic\thoth\classes\services\ThothWorkLinkService;
use PKP\core\JSONMessage;
use PKP\core\PKPContainer;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class ThothPlugin extends \PKP\plugins\GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path);

        if ($success && $this->getEnabled()) {
            $compositionRoot = new ThothCompositionRoot(
                fn (): object => new ThothWorkLinkService(ThothContainer::getInstance()->get('workRepository')),
                fn (): object => ThothContainer::getInstance()->get('bookRegistrationService'),
                function (): array {
                    $bookService = ThothService::book();
                    $abstractService = ThothService::abstract();
                    $chapterService = ThothService::chapter();
                    $contributionService = ThothService::contribution();
                    $languageService = ThothService::language();
                    $referenceService = ThothService::reference();
                    $subjectService = ThothService::subject();
                    $titleService = ThothService::title();
                    $workRelationService = ThothService::workRelation();
                    $chapterWorkMapper = new LegacyChapterWorkMetadataMapper($chapterService->factory);
                    $chapterSynchronizer = new ChapterSynchronizer(
                        new LegacyChapterMetadataGateway($chapterService->repository),
                        $chapterWorkMapper,
                        new WorkSynchronizer(
                            new LegacyWorkMetadataGateway($chapterService->repository),
                            $chapterWorkMapper
                        ),
                        [
                            new TitleSynchronizer(
                                new LegacyTitleMetadataGateway(
                                    $chapterService->repository,
                                    $titleService->repository
                                ),
                                new LegacyChapterTitleMetadataMapper($titleService->factory)
                            ),
                            new AbstractSynchronizer(
                                new LegacyAbstractMetadataGateway(
                                    $chapterService->repository,
                                    $abstractService->repository
                                ),
                                new LegacyChapterAbstractMetadataMapper($abstractService->factory)
                            ),
                            new ContributionSynchronizer(
                                new LegacyContributionMetadataGateway($contributionService),
                                new LegacyChapterContributionMetadataMapper($contributionService)
                            ),
                            new PublicationSynchronizer(
                                new LegacyPublicationMetadataGateway(ThothService::publication()),
                                new LegacyChapterPublicationMetadataMapper(ThothService::publication()),
                                new SynchronizeLocations(
                                    new LegacyLocationMetadataGateway(ThothService::location()->repository)
                                )
                            ),
                        ]
                    );

                    return [
                        new WorkSynchronizer(
                            new LegacyWorkMetadataGateway($bookService->repository),
                            new LegacyWorkMetadataMapper($bookService->factory)
                        ),
                        new TitleSynchronizer(
                            new LegacyTitleMetadataGateway(
                                $bookService->repository,
                                $titleService->repository
                            ),
                            new LegacyTitleMetadataMapper($titleService->factory)
                        ),
                        new AbstractSynchronizer(
                            new LegacyAbstractMetadataGateway(
                                $bookService->repository,
                                $abstractService->repository
                            ),
                            new LegacyAbstractMetadataMapper($abstractService->factory)
                        ),
                        new FrontcoverSynchronizer(
                            new LegacyFrontcoverGateway(ThothService::frontcover())
                        ),
                        new ContributionSynchronizer(
                            new LegacyContributionMetadataGateway($contributionService),
                            new LegacyContributionMetadataMapper($contributionService)
                        ),
                        new PublicationSynchronizer(
                            new LegacyPublicationMetadataGateway(ThothService::publication()),
                            new LegacyPublicationMetadataMapper(ThothService::publication()),
                            new SynchronizeLocations(
                                new LegacyLocationMetadataGateway(ThothService::location()->repository)
                            )
                        ),
                        new LanguageSynchronizer(
                            new LegacyLanguageMetadataGateway($languageService->repository),
                            new LegacyLanguageMetadataMapper()
                        ),
                        new SubjectSynchronizer(
                            new LegacySubjectMetadataGateway($subjectService->repository),
                            new LegacySubjectMetadataMapper(new ThothSubjectClassifier())
                        ),
                        new ReferenceSynchronizer(
                            new LegacyReferenceMetadataGateway($referenceService->repository),
                            new LegacyReferenceMetadataMapper(DAORegistry::getDAO('CitationDAO'))
                        ),
                        new WorkRelationSynchronizer(
                            new LegacyWorkRelationMetadataGateway(
                                $workRelationService->repository,
                                $chapterSynchronizer
                            ),
                            new LegacyWorkRelationMetadataMapper(
                                DAORegistry::getDAO('ChapterDAO'),
                                $chapterWorkMapper
                            )
                        ),
                    ];
                },
                fn (): object => ThothContainer::getInstance()->get('featureVideoService'),
                fn (): object => ThothRepository::publication(),
                Repo::publication(),
                Repo::submission(),
                Application::get()->getRequest(),
                new ThothNotification()
            );
            $compositionRoot->register(PKPContainer::getInstance());

            $hookRegistrant = new HookRegistrant(
                $this,
                PKPContainer::getInstance()->make(GetWorkStatusController::class),
                PKPContainer::getInstance()->make(RegisterBookController::class),
                PKPContainer::getInstance()->make(SynchronizeMetadataController::class),
                PKPContainer::getInstance()->make(UnlinkWorkController::class),
                PKPContainer::getInstance()->make(UploadFeatureVideoController::class),
                PKPContainer::getInstance()->make(GetCatalogFiles::class),
                PKPContainer::getInstance()->make(UploadPublicationFile::class),
                PKPContainer::getInstance()->make(PublicationPublishListener::class)
            );
            $hookRegistrant->register();
        }

        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.thoth.name');
    }

    public function getDescription()
    {
        return __('plugins.generic.thoth.description');
    }

    public function getActions($request, $verb)
    {
        $parentActions = parent::getActions($request, $verb);

        if (!$this->getEnabled()) {
            return $parentActions;
        }

        $router = $request->getRouter();
        $linkAction = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url(
                    $request,
                    null,
                    null,
                    'manage',
                    null,
                    [
                        'verb' => 'settings',
                        'plugin' => $this->getName(),
                        'category' => 'generic'
                    ]
                ),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        );

        array_unshift($parentActions, $linkAction);

        return $parentActions;
    }

    public function manage($args, $request)
    {
        if ($request->getUserVar('verb') !== 'settings') {
            return parent::manage($args, $request);
        }

        $context = $request->getContext();
        $form = new ThothSettingsForm($this, $context->getId());

        if ($request->getUserVar('save')) {
            $form->readInputData();
            if ($form->validate()) {
                $form->execute();
                return new JSONMessage(true);
            }
        } else {
            $form->initData();
        }

        return new JSONMessage(true, $form->fetch($request));
    }
}

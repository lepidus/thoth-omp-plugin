<?php

/**
 * @file plugins/generic/thoth/classes/services/ThothBookRegistrationService.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothBookRegistrationService
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Coordinates full Thoth book registration workflows
 */

namespace APP\plugins\generic\thoth\classes\services;

use APP\plugins\generic\thoth\classes\Contracts\BookRegistrar;
use APP\plugins\generic\thoth\classes\Domain\Identifier\ImprintId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Registration\BookRegistrationPolicy;
use APP\plugins\generic\thoth\classes\Domain\Result\RegistrationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationWarning;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\ThothErrorTranslator;
use ThothApi\Exception\QueryException;

class ThothBookRegistrationService implements BookRegistrar
{
    private $factory;
    private $repository;
    private ThothAbstractService $abstractService;
    private ThothContributionService $contributionService;
    private ThothLanguageService $languageService;
    private ThothPublicationService $publicationService;
    private ThothReferenceService $referenceService;
    private ThothSubjectService $subjectService;
    private ThothTitleService $titleService;
    private ThothWorkRelationService $workRelationService;
    private ?ThothFrontcoverService $frontcoverService;
    private BookRegistrationPolicy $registrationPolicy;
    private ThothErrorTranslator $errorTranslator;

    public function __construct(
        $factory,
        $repository,
        ThothAbstractService $abstractService,
        ThothContributionService $contributionService,
        ThothLanguageService $languageService,
        ThothPublicationService $publicationService,
        ThothReferenceService $referenceService,
        ThothSubjectService $subjectService,
        ThothTitleService $titleService,
        ThothWorkRelationService $workRelationService,
        ?ThothFrontcoverService $frontcoverService = null,
        ?BookRegistrationPolicy $registrationPolicy = null,
        ?ThothErrorTranslator $errorTranslator = null
    ) {
        $this->factory = $factory;
        $this->repository = $repository;
        $this->abstractService = $abstractService;
        $this->contributionService = $contributionService;
        $this->languageService = $languageService;
        $this->publicationService = $publicationService;
        $this->referenceService = $referenceService;
        $this->subjectService = $subjectService;
        $this->titleService = $titleService;
        $this->workRelationService = $workRelationService;
        $this->frontcoverService = $frontcoverService;
        $this->registrationPolicy = $registrationPolicy ?? new BookRegistrationPolicy();
        $this->errorTranslator = $errorTranslator ?? new ThothErrorTranslator();
    }

    public function register(object $publication, ImprintId $imprintId): RegistrationResult
    {
        try {
            $thothBook = $this->factory->createFromPublication($publication);
            $thothBook->setImprintId($imprintId->toString());

            $thothBook->setWorkStatus($this->registrationPolicy->initialWorkStatus());

            $thothBookId = $this->repository->add($thothBook);
            $publication->setData('thothBookId', $thothBookId);

            $this->registerMetadata($publication, $thothBookId);

            $this->contributionService->registerByPublication($publication);
            $this->publicationService->registerByPublication($publication);
            $this->languageService->registerByPublication($publication);
            $this->subjectService->registerByPublication($publication);
            $this->referenceService->registerByPublication($publication);
            $this->workRelationService->registerByPublication($publication, $imprintId->toString());
            $warning = $this->frontcoverService?->sync($publication, $thothBookId);
        } catch (QueryException $exception) {
            throw $this->errorTranslator->registrationFailure($exception);
        }

        $synchronizationResult = new SynchronizationResult();
        if ($warning) {
            $synchronizationResult = $synchronizationResult->withWarning(new SynchronizationWarning($warning));
        }

        return new RegistrationResult(new WorkId($thothBookId), $synchronizationResult);
    }

    public function rollback(object $publication): void
    {
        $workId = $publication->getData('thothBookId');
        if (!$workId) {
            return;
        }

        $this->repository->delete($workId);
        $publication->setData('thothBookId', null);
    }

    private function registerMetadata($publication, string $thothBookId): void
    {
        $this->titleService->registerByPublication(
            $publication,
            $thothBookId,
            $publication->getData('locale')
        );
        $this->abstractService->registerByPublication(
            $publication,
            $thothBookId,
            $publication->getData('locale')
        );
    }
}

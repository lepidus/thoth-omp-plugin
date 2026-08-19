<?php


/**
 * @file plugins/generic/thoth/tests/classes/container/providers/ThothServiceProvider.inc.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothServiceProvider
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Utility class to package all plugin container bindings for services
 */

namespace APP\plugins\generic\thoth\classes\container\providers;

use APP\plugins\generic\thoth\classes\factories\ThothAbstractFactory;
use APP\plugins\generic\thoth\classes\factories\ThothBiographyFactory;
use APP\plugins\generic\thoth\classes\factories\ThothBookFactory;
use APP\plugins\generic\thoth\classes\factories\ThothChapterFactory;
use APP\plugins\generic\thoth\classes\factories\ThothContributionFactory;
use APP\plugins\generic\thoth\classes\factories\ThothContributorFactory;
use APP\plugins\generic\thoth\classes\factories\ThothLocationFactory;
use APP\plugins\generic\thoth\classes\factories\ThothPublicationFactory;
use APP\plugins\generic\thoth\classes\factories\ThothTitleFactory;
use APP\plugins\generic\thoth\classes\services\ThothAbstractService;
use APP\plugins\generic\thoth\classes\services\ThothAffiliationService;
use APP\plugins\generic\thoth\classes\services\ThothBiographyService;
use APP\plugins\generic\thoth\classes\services\ThothBookRegistrationService;
use APP\plugins\generic\thoth\classes\services\ThothBookService;
use APP\plugins\generic\thoth\classes\services\ThothChapterService;
use APP\plugins\generic\thoth\classes\services\ThothContributionService;
use APP\plugins\generic\thoth\classes\services\ThothContributorService;
use APP\plugins\generic\thoth\classes\services\ThothFeatureVideoService;
use APP\plugins\generic\thoth\classes\services\ThothFileUploadService;
use APP\plugins\generic\thoth\classes\services\ThothFrontcoverService;
use APP\plugins\generic\thoth\classes\services\ThothLanguageService;
use APP\plugins\generic\thoth\classes\services\ThothLocationService;
use APP\plugins\generic\thoth\classes\services\ThothMeService;
use APP\plugins\generic\thoth\classes\services\ThothPublicationService;
use APP\plugins\generic\thoth\classes\services\ThothReferenceService;
use APP\plugins\generic\thoth\classes\services\ThothSubjectService;
use APP\plugins\generic\thoth\classes\services\ThothTitleService;
use APP\plugins\generic\thoth\classes\services\ThothWorkRelationService;

class ThothServiceProvider implements ContainerProvider
{
    public function register($container)
    {
        $this->singletonClass($container, 'affiliationService', ThothAffiliationService::class, [
            'affiliationRepository',
            'institutionRepository',
        ]);

        $this->singletonClass($container, 'abstractService', ThothAbstractService::class, [
            new ThothAbstractFactory(),
            'abstractRepository',
        ]);

        $this->singletonClass($container, 'biographyService', ThothBiographyService::class, [
            new ThothBiographyFactory(),
            'biographyRepository',
        ]);

        $this->singletonClass($container, 'bookService', ThothBookService::class, [
            new ThothBookFactory(),
            'bookRepository',
            'publicationService',
            'titleService',
            'abstractService',
            'frontcoverService',
        ]);

        $this->singletonClass($container, 'bookRegistrationService', ThothBookRegistrationService::class, [
            new ThothBookFactory(),
            'bookRepository',
            'abstractService',
            'contributionService',
            'languageService',
            'publicationService',
            'referenceService',
            'subjectService',
            'titleService',
            'workRelationService',
            'frontcoverService',
        ]);

        $this->singletonClass($container, 'chapterService', ThothChapterService::class, [
            new ThothChapterFactory(),
            'chapterRepository',
            'contributionService',
            'publicationService',
            'titleService',
            'abstractService',
        ]);

        $this->singletonClass($container, 'contributionService', ThothContributionService::class, [
            new ThothContributionFactory(),
            'contributionRepository',
            'contributorRepository',
            'contributorService',
            'biographyService',
            'affiliationService',
        ]);

        $this->singletonClass($container, 'contributorService', ThothContributorService::class, [
            new ThothContributorFactory(),
            'contributorRepository',
        ]);

        $this->singletonClass($container, 'fileUploadService', ThothFileUploadService::class);

        $this->singletonClass($container, 'featureVideoService', ThothFeatureVideoService::class, [
            'featureVideoRepository',
            'featureVideoFileUploadRepository',
            'fileUploadService',
        ]);

        $this->singletonClass($container, 'frontcoverService', ThothFrontcoverService::class, [
            'frontcoverFileUploadRepository',
            'workRepository',
            'fileUploadService',
            'meService',
        ]);

        $this->singletonClass($container, 'languageService', ThothLanguageService::class, [
            'languageRepository',
        ]);

        $this->singletonClass($container, 'locationService', ThothLocationService::class, [
            new ThothLocationFactory(),
            'locationRepository',
        ]);

        $this->singletonClass($container, 'meService', ThothMeService::class, [
            'meRepository',
        ]);

        $this->singletonClass($container, 'publicationService', ThothPublicationService::class, [
            new ThothPublicationFactory(),
            'publicationRepository',
            'locationService',
        ]);

        $this->singletonClass($container, 'referenceService', ThothReferenceService::class, [
            'referenceRepository',
        ]);

        $this->singletonClass($container, 'subjectService', ThothSubjectService::class, [
            'subjectRepository',
        ]);

        $this->singletonClass($container, 'titleService', ThothTitleService::class, [
            new ThothTitleFactory(),
            'titleRepository',
        ]);

        $this->singletonClass($container, 'workRelationService', ThothWorkRelationService::class, [
            'workRelationRepository',
            'chapterService',
        ]);
    }

    private function singletonClass($container, $id, $className, array $dependencies = [])
    {
        $container->singleton($id, function ($container) use ($className, $dependencies) {
            $resolvedDependencies = array_map(
                function ($dependency) use ($container) {
                    return is_string($dependency) ? $container->make($dependency) : $dependency;
                },
                $dependencies
            );

            return new $className(...$resolvedDependencies);
        });
    }
}

<?php

import('plugins.generic.thoth.classes.container.providers.ContainerProvider');
import('plugins.generic.thoth.classes.factories.ThothAbstractFactory');
import('plugins.generic.thoth.classes.factories.ThothBiographyFactory');
import('plugins.generic.thoth.classes.factories.ThothBookFactory');
import('plugins.generic.thoth.classes.factories.ThothChapterFactory');
import('plugins.generic.thoth.classes.factories.ThothContributionFactory');
import('plugins.generic.thoth.classes.factories.ThothContributorFactory');
import('plugins.generic.thoth.classes.factories.ThothLocationFactory');
import('plugins.generic.thoth.classes.factories.ThothPublicationFactory');
import('plugins.generic.thoth.classes.factories.ThothTitleFactory');
import('plugins.generic.thoth.classes.services.ThothAbstractService');
import('plugins.generic.thoth.classes.services.ThothAffiliationService');
import('plugins.generic.thoth.classes.services.ThothBiographyService');
import('plugins.generic.thoth.classes.services.ThothBookRegistrationService');
import('plugins.generic.thoth.classes.services.ThothBookService');
import('plugins.generic.thoth.classes.services.ThothChapterService');
import('plugins.generic.thoth.classes.services.ThothContributionService');
import('plugins.generic.thoth.classes.services.ThothContributorService');
import('plugins.generic.thoth.classes.services.ThothFileUploadService');
import('plugins.generic.thoth.classes.services.ThothFeatureVideoService');
import('plugins.generic.thoth.classes.services.ThothFrontcoverService');
import('plugins.generic.thoth.classes.services.ThothLanguageService');
import('plugins.generic.thoth.classes.services.ThothLocationService');
import('plugins.generic.thoth.classes.services.ThothPublicationService');
import('plugins.generic.thoth.classes.services.ThothReferenceService');
import('plugins.generic.thoth.classes.services.ThothSubjectService');
import('plugins.generic.thoth.classes.services.ThothTitleService');
import('plugins.generic.thoth.classes.services.ThothWorkRelationService');

class ThothServiceProvider implements ContainerProvider
{
    public function register($container)
    {
        $this->singletonClass($container, 'abstractService', ThothAbstractService::class, [
            new ThothAbstractFactory(),
            'abstractRepository',
        ]);

        $this->singletonClass($container, 'affiliationService', ThothAffiliationService::class, [
            'affiliationRepository',
            'institutionRepository',
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
        ]);

        $this->singletonClass($container, 'languageService', ThothLanguageService::class, [
            'languageRepository',
        ]);

        $this->singletonClass($container, 'locationService', ThothLocationService::class, [
            new ThothLocationFactory(),
            'locationRepository',
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

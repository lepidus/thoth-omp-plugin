<?php


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

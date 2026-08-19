<?php

/**
 * @file plugins/generic/thoth/tests/classes/container/providers/ThothRepositoryProvider.inc.php
 *
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothRepositoryProvider
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Utility class to package all plugin container bindings for repositories
 */

require_once(__DIR__ . '/../../../vendor/autoload.php');

use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\ThothClientFactory;

import('plugins.generic.thoth.classes.container.providers.ContainerProvider');
import('plugins.generic.thoth.classes.factories.ThothBookFactory');
import('plugins.generic.thoth.classes.factories.ThothChapterFactory');
import('plugins.generic.thoth.classes.factories.ThothContributionFactory');
import('plugins.generic.thoth.classes.factories.ThothContributorFactory');
import('plugins.generic.thoth.classes.factories.ThothLocationFactory');
import('plugins.generic.thoth.classes.factories.ThothPublicationFactory');
import('plugins.generic.thoth.classes.repositories.ThothAbstractRepository');
import('plugins.generic.thoth.classes.repositories.ThothAffiliationRepository');
import('plugins.generic.thoth.classes.repositories.ThothBiographyRepository');
import('plugins.generic.thoth.classes.repositories.ThothBookRepository');
import('plugins.generic.thoth.classes.repositories.ThothChapterRepository');
import('plugins.generic.thoth.classes.repositories.ThothContributionRepository');
import('plugins.generic.thoth.classes.repositories.ThothContributorRepository');
import('plugins.generic.thoth.classes.repositories.ThothFrontcoverFileUploadRepository');
import('plugins.generic.thoth.classes.repositories.ThothFeatureVideoFileUploadRepository');
import('plugins.generic.thoth.classes.repositories.ThothFeatureVideoRepository');
import('plugins.generic.thoth.classes.repositories.ThothImprintRepository');
import('plugins.generic.thoth.classes.repositories.ThothInstitutionRepository');
import('plugins.generic.thoth.classes.repositories.ThothLanguageRepository');
import('plugins.generic.thoth.classes.repositories.ThothLocationRepository');
import('plugins.generic.thoth.classes.repositories.ThothMeRepository');
import('plugins.generic.thoth.classes.repositories.ThothPublicationRepository');
import('plugins.generic.thoth.classes.repositories.ThothPublicationFileUploadRepository');
import('plugins.generic.thoth.classes.repositories.ThothReferenceRepository');
import('plugins.generic.thoth.classes.repositories.ThothSubjectRepository');
import('plugins.generic.thoth.classes.repositories.ThothTitleRepository');
import('plugins.generic.thoth.classes.repositories.ThothWorkRelationRepository');
import('plugins.generic.thoth.classes.repositories.ThothWorkRepository');
import('plugins.generic.thoth.classes.security.ThothApiUrlValidator');

class ThothRepositoryProvider implements ContainerProvider
{
    public function register($container)
    {
        $container->singleton('clientFactory', function () {
            return new ThothClientFactory(
                new LegacyThothConfigurationRepository(),
                new ThothApiUrlValidator()
            );
        });

        $container->bind('client', function ($container) {
            $contextId = Application::get()->getRequest()->getContext()->getId();
            return $container->make('clientFactory')->create($contextId);
        });

        $container->singleton('meRepository', function ($container) {
            return new ThothMeRepository($container->make('client'));
        });

        $container->singleton('abstractRepository', function ($container) {
            return new ThothAbstractRepository($container->make('client'));
        });

        $container->singleton('affiliationRepository', function ($container) {
            return new ThothAffiliationRepository($container->make('client'));
        });

        $container->singleton('bookRepository', function ($container) {
            return new ThothBookRepository($container->make('client'));
        });

        $container->singleton('biographyRepository', function ($container) {
            return new ThothBiographyRepository($container->make('client'));
        });

        $container->singleton('chapterRepository', function ($container) {
            return new ThothChapterRepository($container->make('client'));
        });

        $container->singleton('contributionRepository', function ($container) {
            return new ThothContributionRepository($container->make('client'));
        });

        $container->singleton('contributorRepository', function ($container) {
            return new ThothContributorRepository($container->make('client'));
        });

        $container->singleton('imprintRepository', function ($container) {
            return new ThothImprintRepository($container->make('client'));
        });

        $container->singleton('institutionRepository', function ($container) {
            return new ThothInstitutionRepository($container->make('client'));
        });

        $container->singleton('languageRepository', function ($container) {
            return new ThothLanguageRepository($container->make('client'));
        });

        $container->singleton('locationRepository', function ($container) {
            return new ThothLocationRepository($container->make('client'));
        });

        $container->singleton('publicationRepository', function ($container) {
            return new ThothPublicationRepository($container->make('client'));
        });

        $container->singleton('publicationFileUploadRepository', function ($container) {
            return new ThothPublicationFileUploadRepository($container->make('client'));
        });

        $container->singleton('frontcoverFileUploadRepository', function ($container) {
            return new ThothFrontcoverFileUploadRepository($container->make('client'));
        });

        $container->singleton('featureVideoRepository', function ($container) {
            return new ThothFeatureVideoRepository($container->make('client'));
        });

        $container->singleton('featureVideoFileUploadRepository', function ($container) {
            return new ThothFeatureVideoFileUploadRepository($container->make('client'));
        });

        $container->singleton('referenceRepository', function ($container) {
            return new ThothReferenceRepository($container->make('client'));
        });

        $container->singleton('subjectRepository', function ($container) {
            return new ThothSubjectRepository($container->make('client'));
        });

        $container->singleton('titleRepository', function ($container) {
            return new ThothTitleRepository($container->make('client'));
        });

        $container->singleton('workRelationRepository', function ($container) {
            return new ThothWorkRelationRepository($container->make('client'));
        });

        $container->singleton('workRepository', function ($container) {
            return new ThothWorkRepository($container->make('client'));
        });
    }
}

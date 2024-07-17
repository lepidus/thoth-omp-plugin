<?php

/**
 * @file plugins/generic/thoth/classes/services/WorkService.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WorkService
 * @ingroup plugins_generic_thoth
 *
 * @brief Helper class that encapsulates business logic for Thoth works
 */

import('plugins.generic.thoth.thoth.models.Work');

class WorkService
{
    public function getPropertiesBySubmission($submission, $request)
    {
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();
        $publication = $submission->getCurrentPublication();

        $props = [];
        $props['workType'] = $this->getWorkTypeBySubmissionWorkType($submission->getData('workType'));
        $props['workStatus'] = Work::WORK_STATUS_ACTIVE;
        $props['fullTitle'] = $publication->getLocalizedFullTitle();
        $props['title'] = $publication->getLocalizedTitle();
        $props['subtitle'] = $publication->getLocalizedData('subtitle');
        $props['longAbstract'] = $publication->getLocalizedData('abstract');
        $props['edition'] = $publication->getData('version');
        $props['doi'] = $publication->getStoredPubId('doi');
        $props['publicationDate'] = $publication->getData('datePublished');
        $props['license'] = $publication->getData('licenseUrl');
        $props['copyrightHolder'] = $publication->getLocalizedData('copyrightHolder');
        $props['coverUrl'] = $publication->getLocalizedCoverImageUrl($context->getId());
        $props['landingPage'] = $dispatcher->url(
            $request,
            ROUTE_PAGE,
            $context->getPath(),
            'catalog',
            'book',
            $submission->getBestId()
        );

        return $props;
    }

    public function new($params)
    {
        $work = new Work();
        $work->setWorkType($params['workType']);
        $work->setWorkStatus($params['workStatus']);
        $work->setFullTitle($params['fullTitle']);
        $work->setTitle($params['title']);
        $work->setLongAbstract($params['longAbstract'] ?? null);
        $work->setEdition($params['edition'] ?? null);
        $work->setPublicationDate($params['publicationDate'] ?? null);
        $work->setSubtitle($params['subtitle'] ?? null);
        $work->setPageCount($params['pageCount'] ?? null);
        $work->setDoi($params['doi'] ?? null);
        $work->setLicense($params['license'] ?? null);
        $work->setCopyrightHolder($params['copyrightHolder'] ?? null);
        $work->setLandingPage($params['landingPage'] ?? null);
        $work->setCoverUrl($params['coverUrl'] ?? null);

        return $work;
    }

    public function getWorkTypeBySubmissionWorkType($submissionWorkType)
    {
        $workTypeMapping = [
            WORK_TYPE_EDITED_VOLUME => Work::WORK_TYPE_EDITED_BOOK,
            WORK_TYPE_AUTHORED_WORK => Work::WORK_TYPE_MONOGRAPH
        ];

        return $workTypeMapping[$submissionWorkType];
    }
}

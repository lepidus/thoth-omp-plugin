<?php

/**
 * @file plugins/generic/thoth/classes/ThothValidator.php
 *
 * Copyright (c) 2025 Lepidus Tecnologia
 * Copyright (c) 2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothValidator
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Validate submission metadata to Thoth submit
 */

require_once(__DIR__ . '/../vendor/autoload.php');

use Biblys\Isbn\Isbn;
use ThothApi\GraphQL\Models\Work as ThothWork;

import('plugins.generic.thoth.classes.facades.ThothService');

class ThothValidator
{
    public static function validate($submission)
    {
        $errors = [];

        $publication = $submission->getCurrentPublication();
        $doi = $publication->getData('doiObject')?->getResolvingUrl();

        if ($doi !== null) {
            $errors = array_merge($errors, self::validateDoiExists($doi));
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();

        $landingPage = $dispatcher->url(
            $request,
            ROUTE_PAGE,
            $context->getPath(),
            'catalog',
            'book',
            $submission->getBestId()
        );

        $errors = array_merge($errors, self::validateLandingPageExists($landingPage));

        $publicationFormats = Application::getRepresentationDao()
            ->getApprovedByPublicationId($submission->getData('currentPublicationId'))
            ->toArray();

        $publicationFormats = array_filter($publicationFormats, function ($publicationFormat) {
            return $publicationFormat->getIsAvailable();
        });

        $errors = array_merge($errors, self::validateIsbn($publicationFormats));

        return $errors;
    }

    public static function validateIsbn($publicationFormats)
    {
        $errors = [];
        foreach ($publicationFormats as $publicationFormat) {
            try {
                $isbn = ThothService::publication()->getIsbnByPublicationFormat($publicationFormat);
                if ($isbn !== null) {
                    Isbn::validateAsIsbn13($isbn);
                }
                $errors = array_merge($errors, self::validateIsbnExists($isbn));
            } catch (Exception $e) {
                $errors[] = __('plugins.generic.thoth.validation.isbn', [
                    'isbn' => $isbn,
                    'formatName' => $publicationFormat->getLocalizedName()
                ]);
            }
        }

        return $errors;
    }

    public static function validateDoiExists($doi)
    {
        $errors = [];

        try {
            $work = ThothService::work()->getByDoi($doi);
            if ($work instanceof ThothWork) {
                $errors[] = __('plugins.generic.thoth.validation.doiExists', ['doi' => $doi]);
            }
        } catch (Exception $e) {
            return $errors;
        }

        return $errors;
    }

    public static function validateLandingPageExists($landingPage)
    {
        $errors = [];

        $works = ThothService::work()->search($landingPage);

        if (!empty($works)) {
            $errors[] = __('plugins.generic.thoth.validation.landingPageExists', ['landingPage' => $landingPage]);
        }

        return $errors;
    }

    public static function validateIsbnExists($isbn)
    {
        $errors = [];

        $publications = ThothService::publication()->search($isbn);

        if (!empty($publications)) {
            $errors[] = __('plugins.generic.thoth.validation.isbnExists', ['isbn' => $isbn]);
        }

        return $errors;
    }
}

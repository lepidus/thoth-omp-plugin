<?php

/**
 * @file plugins/generic/thoth/classes/services/ThothPublicationService.inc.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Copyright (c) 2024 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothPublicationService
 * @ingroup plugins_generic_thoth
 *
 * @brief Helper class that encapsulates business logic for Thoth publications
 */

import('plugins.generic.thoth.classes.services.ThothLocationService');
import('plugins.generic.thoth.thoth.models.ThothPublication');

class ThothPublicationService
{
    public function newByPublicationFormat($publicationFormat)
    {
        $params = [];
        $params['publicationType'] = $this->getPublicationTypeByPublicationFormat($publicationFormat);
        $params['isbn'] = $this->getIsbnByPublicationFormat($publicationFormat);

        return $this->new($params);
    }

    public function new($params)
    {
        $publication = new ThothPublication();
        $publication->setPublicationType($params['publicationType']);
        $publication->setIsbn($params['isbn'] ?? null);

        return $publication;
    }

    public function register($thothClient, $publicationFormat, $workId, $chapterId = null)
    {
        $thothPublication = $this->newByPublicationFormat($publicationFormat);
        $thothPublication->setWorkId($workId);

        if ($chapterId && $thothPublication->getIsbn()) {
            $thothPublication->setIsbn(null);
        }

        $thothPublicationId = $thothClient->createPublication($thothPublication);
        $thothPublication->setId($thothPublicationId);

        $thothLocationService = new ThothLocationService();
        if ($publicationFormat->getRemoteUrl()) {
            $thothLocationService->register($thothClient, $publicationFormat, $thothPublicationId);
            return $thothPublication;
        }

        $files = array_filter(
            iterator_to_array(Services::get('submissionFile')->getMany([
                'assocTypes' => [ASSOC_TYPE_PUBLICATION_FORMAT],
                'assocIds' => [$publicationFormat->getId()],
            ])),
            function ($file) use ($chapterId) {
                return $file->getData('chapterId') == $chapterId;
            }
        );

        $canonical = true;
        foreach ($files as $file) {
            $thothLocationService->register($thothClient, $publicationFormat, $thothPublicationId, $file->getId(), $canonical);
            $canonical = false;
        }

        return $thothPublication;
    }

    public function getPublicationTypeByPublicationFormat($publicationFormat)
    {
        $publicationTypeMapping = [
            'BC' => ThothPublication::PUBLICATION_TYPE_PAPERBACK,
            'BB' => ThothPublication::PUBLICATION_TYPE_HARDBACK,
            'DA' => [
                'html' => ThothPublication::PUBLICATION_TYPE_HTML,
                'pdf' => ThothPublication::PUBLICATION_TYPE_PDF,
                'xml' => ThothPublication::PUBLICATION_TYPE_XML,
                'epub' => ThothPublication::PUBLICATION_TYPE_EPUB,
                'mobi' => ThothPublication::PUBLICATION_TYPE_MOBI,
                'azw3' => ThothPublication::PUBLICATION_TYPE_AZW3,
                'docx' => ThothPublication::PUBLICATION_TYPE_DOCX,
                'fictionbook' => ThothPublication::PUBLICATION_TYPE_FICTION_BOOK,
            ]
        ];

        $entryKey = $publicationFormat->getEntryKey();
        if ($entryKey != 'DA') {
            return $publicationTypeMapping[$entryKey];
        }

        $formatName = $publicationFormat->getLocalizedName();
        $formatName = trim(
            preg_replace(
                "/[^a-z0-9\.\-]+/",
                "",
                str_replace(
                    [' ', '_', ':'],
                    '',
                    strtolower($formatName)
                )
            )
        );

        return $publicationTypeMapping[$entryKey][$formatName] ?? ThothPublication::PUBLICATION_TYPE_PDF;
    }

    public function getIsbnByPublicationFormat($publicationFormat)
    {
        $identificationCodes = $publicationFormat->getIdentificationCodes()->toArray();
        foreach ($identificationCodes as $identificationCode) {
            if ($identificationCode->getCode() == "15" || $identificationCode->getCode() == "24") {
                return $identificationCode->getValue();
            }
        }

        return null;
    }
}

<?php

/**
 * @file plugins/generic/thoth/classes/schema/ThothSchema.inc.php
 *
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothSchema
 * @ingroup plugins_generic_thoth
 *
 * @brief Class to add Thoth properties for storage in the database
 */

class ThothSchema
{
    /** Submission props written only by the plugin; never through the API. */
    public const API_WRITE_DISABLED_SUBMISSION_PROPS = ['thothWorkId'];

    /** Publication props written only by the plugin (frontcover CDN sync); never through the API. */
    public const API_WRITE_DISABLED_PUBLICATION_PROPS = ['thothFrontcoverSha256', 'thothFrontcoverUrl'];

    public function addWorkIdToSchema($hookName, $args)
    {
        $schema = & $args[0];

        $schema->properties->{'thothWorkId'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        return false;
    }

    /**
     * OMP 3.3 has no writeDisabledInApi, and readOnly props are dropped by the
     * DAO, so API writes are rejected on validation. Internal edits do not validate.
     */
    public function rejectApiWritesToSubmissionProps($hookName, $args)
    {
        $errors = & $args[0];
        $this->addApiWriteErrors($errors, $args[2], self::API_WRITE_DISABLED_SUBMISSION_PROPS);

        return false;
    }

    public function rejectApiWritesToPublicationProps($hookName, $args)
    {
        $errors = & $args[0];
        $this->addApiWriteErrors($errors, $args[2], self::API_WRITE_DISABLED_PUBLICATION_PROPS);

        return false;
    }

    private function addApiWriteErrors(&$errors, $props, $propNames)
    {
        foreach ($propNames as $propName) {
            if (array_key_exists($propName, (array) $props)) {
                $errors[$propName] = [__('api.400.paramNotSupported', ['param' => $propName])];
            }
        }
    }

    public function addToPublicationSchema($hookName, $args)
    {
        $schema = & $args[0];

        $schema->properties->{'place'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        $schema->properties->{'pageCount'} = (object) [
            'type' => 'integer',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        $schema->properties->{'imageCount'} = (object) [
            'type' => 'integer',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        $schema->properties->{'thothUploadFrontcover'} = (object) [
            'type' => 'boolean',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        $schema->properties->{'thothFrontcoverSha256'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        $schema->properties->{'thothFrontcoverUrl'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        return false;
    }

    public function addToAuthorSchema($hookName, $args)
    {
        $schema = & $args[0];

        $schema->properties->{'mainContribution'} = (object) [
            'type' => 'boolean',
            'apiSummary' => true,
            'validation' => ['nullable']
        ];

        return false;
    }

    public function addToBackendProps($hookName, $args)
    {
        $props = & $args[0];
        $props[] = 'thothWorkId';

        return false;

    }

    public function addToAdditionalFieldNames($hookName, $params)
    {
        $fields = & $params[1];
        $fields[] = 'mainContribution';

        return false;
    }
}

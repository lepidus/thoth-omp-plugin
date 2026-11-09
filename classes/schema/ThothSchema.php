<?php

/**
 * @file plugins/generic/thoth/classes/schema/ThothSchema.inc.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothSchema
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Class to add Thoth properties for storage in the database
 */

namespace APP\plugins\generic\thoth\classes\schema;

class ThothSchema
{
    /** Publication props written only by the plugin (frontcover CDN sync); never through the API. */
    public const API_WRITE_DISABLED_PUBLICATION_PROPS = ['thothFrontcoverSha256', 'thothFrontcoverUrl'];

    public function addWorkIdToSchema($hookName, $args)
    {
        $schema = & $args[0];
        // Not readOnly: the DAO drops readOnly props, and the plugin persists this value itself.
        $schema->properties->{'thothWorkId'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'writeDisabledInApi' => true,
            'validation' => ['nullable'],
        ];
        return false;
    }

    public function addReasonToSchema($hookName, $args)
    {
        $schema = & $args[0];
        $schema->properties->{'reason'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        return false;
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

    public function rejectApiWritesToPublicationProps($hookName, $args)
    {
        $errors = & $args[0];
        $props = $args[2];

        foreach (self::API_WRITE_DISABLED_PUBLICATION_PROPS as $propName) {
            if (array_key_exists($propName, $props)) {
                $errors[$propName] = [__('api.400.propReadOnly', ['prop' => $propName])];
            }
        }

        return false;
    }

    public function addToAuthorSchema($hookName, $args)
    {
        $schema = & $args[0];

        $schema->properties->{'mainContribution'} = (object) [
            'type' => 'boolean',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        return false;
    }

    public function addToSubmissionsListProps($hookName, $args)
    {
        $props = & $args[0];

        $props[] = 'thothWorkId';
    }
}

<?php

/**
 * @file plugins/generic/thoth/tests/classes/schema/ThothSchemaTest.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothSchemaTest
 *
 * @ingroup plugins_generic_thoth_tests
 *
 * @see ThothSchema
 *
 * @brief Test class for the ThothSchema class
 */

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.schema.ThothSchema');

class ThothSchemaTest extends PKPTestCase
{
    public function testAddFrontcoverSyncFieldsToPublicationSchema(): void
    {
        $schema = new stdClass();
        $schema->properties = new stdClass();

        $args = [&$schema];

        (new ThothSchema())->addToPublicationSchema('Schema::get::publication', $args);

        $this->assertSame('boolean', $schema->properties->thothUploadFrontcover->type);
        $this->assertSame('string', $schema->properties->thothFrontcoverSha256->type);
        $this->assertSame('string', $schema->properties->thothFrontcoverUrl->type);
    }

    public function testRejectsWorkIdSubmittedForValidation(): void
    {
        $errors = [];
        $args = [&$errors, 'edit', ['thothWorkId' => 'another-work', 'locale' => 'en_US'], ['en_US'], 'en_US'];

        (new ThothSchema())->rejectApiWritesToSubmissionProps('Submission::validate', $args);

        $this->assertSame(['thothWorkId'], array_keys($errors));
    }

    public function testAcceptsSubmissionValidationWithoutWorkId(): void
    {
        $errors = [];
        $args = [&$errors, 'edit', ['locale' => 'en_US'], ['en_US'], 'en_US'];

        (new ThothSchema())->rejectApiWritesToSubmissionProps('Submission::validate', $args);

        $this->assertSame([], $errors);
    }

    public function testDoesNotAddFeatureVideoFieldsToPublicationSchema(): void
    {
        $schema = new stdClass();
        $schema->properties = new stdClass();
        $args = [&$schema];
        (new ThothSchema())->addToPublicationSchema('Schema::get::publication', $args);

        foreach (['Id', 'Title', 'Url', 'Width', 'Height', 'Sha256'] as $suffix) {
            $this->assertObjectNotHasAttribute('thothFeatureVideo' . $suffix, $schema->properties);
        }
    }
}

<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Schema;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Presentation\Schema\ThothSchema;
use PKP\tests\PKPTestCase;
use stdClass;

final class ThothSchemaTest extends PKPTestCase
{
    public function testPreservesSubmissionEventPublicationAndAuthorProperties(): void
    {
        $thothSchema = new ThothSchema();
        $submission = $this->schema();
        $eventLog = $this->schema();
        $publication = $this->schema();
        $author = $this->schema();

        $this->assertFalse($thothSchema->addWorkIdToSchema('Schema::get::submission', [&$submission]));
        $this->assertFalse($thothSchema->addReasonToSchema('Schema::get::eventLog', [&$eventLog]));
        $this->assertFalse($thothSchema->addToPublicationSchema('Schema::get::publication', [&$publication]));
        $this->assertFalse($thothSchema->addToAuthorSchema('Schema::get::author', [&$author]));

        $this->assertSame('string', $submission->properties->thothWorkId->type);
        $this->assertSame('string', $eventLog->properties->reason->type);
        $this->assertSame('boolean', $publication->properties->thothUploadFrontcover->type);
        $this->assertSame('string', $publication->properties->thothFrontcoverSha256->type);
        $this->assertSame('string', $publication->properties->thothFrontcoverUrl->type);
        $this->assertSame('boolean', $author->properties->mainContribution->type);
    }

    public function testAddsWorkIdToSubmissionListPropertiesWithoutFeatureVideoSchemaFields(): void
    {
        $schema = new ThothSchema();
        $publication = $this->schema();
        $props = ['id'];

        $schema->addToPublicationSchema('Schema::get::publication', [&$publication]);
        $this->assertFalse($schema->addToSubmissionsListProps('Submission::getSubmissionsListProps', [&$props]));

        $this->assertSame(['id', 'thothWorkId'], $props);
        foreach (['Id', 'Title', 'Url', 'Width', 'Height', 'Sha256'] as $suffix) {
            $this->assertFalse(property_exists($publication->properties, 'thothFeatureVideo' . $suffix));
        }
    }

    private function schema(): stdClass
    {
        $schema = new stdClass();
        $schema->properties = new stdClass();

        return $schema;
    }
}

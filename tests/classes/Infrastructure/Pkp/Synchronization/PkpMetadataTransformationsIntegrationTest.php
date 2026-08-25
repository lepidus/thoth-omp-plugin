<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Synchronization;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Localized\PkpLocalizedMetadataReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Works\PkpWorkMetadataReader;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\tests\PKPTestCase;
use ThothApi\GraphQL\Enums\LocaleCode;

final class PkpMetadataTransformationsIntegrationTest extends PKPTestCase
{
    public function testItMapsARealOmpPublicationThroughTheDefinitiveReaders(): void
    {
        $publicationId = (int) DB::table('publications')->value('publication_id');
        $publication = Repo::publication()->get($publicationId);
        $this->assertNotNull($publication, 'The OMP dataset must contain a publication');

        $submission = Repo::submission()->get((int) $publication->getData('submissionId'));
        $this->assertNotNull($submission, 'The publication must belong to a submission');

        $localizedReader = new PkpLocalizedMetadataReader();
        $titles = $localizedReader->titles($publication, $submission->getData('locale'));
        $work = (new PkpWorkMetadataReader(
            Repo::submission(),
            Repo::publication(),
            DAORegistry::getDAO('PressDAO'),
            DAORegistry::getDAO('PublicationFormatDAO'),
            Application::get()->getRequest()
        ))->fromPublication($publication);

        $this->assertNotEmpty($titles);
        $this->assertNotEmpty($titles[0]['title']);
        $this->assertContains($titles[0]['localeCode'], LocaleCode::definition()->getValues());
        $this->assertContains($work['workType'], ['MONOGRAPH', 'EDITED_BOOK']);
        $this->assertSame('FORTHCOMING', $work['workStatus']);
        $this->assertStringContainsString('/catalog/book/', $work['landingPage']);
    }
}

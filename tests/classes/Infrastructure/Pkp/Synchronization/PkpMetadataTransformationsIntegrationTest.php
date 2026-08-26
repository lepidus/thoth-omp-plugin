<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use ThothApi\GraphQL\Enums\LocaleCode;

import('lib.pkp.tests.PKPTestCase');
import('classes.core.PageRouter');

final class PkpMetadataTransformationsIntegrationTest extends PKPTestCase
{
    private $previousRouter;
    private $previousDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $request = Application::get()->getRequest();
        $this->previousRouter = $request->getRouter();
        $this->previousDispatcher = $request->getDispatcher();
        if ($this->previousDispatcher === null) {
            $request->setDispatcher(Application::get()->getDispatcher());
        }
        if ($this->previousRouter === null) {
            $router = new PageRouter();
            $router->setApplication(Application::get());
            $router->setDispatcher(Application::get()->getDispatcher());
            $request->setRouter($router);
        }
    }

    protected function tearDown(): void
    {
        $request = Application::get()->getRequest();
        $request->setRouter($this->previousRouter);
        $request->setDispatcher($this->previousDispatcher);
        parent::tearDown();
    }

    public function testItMapsARealOmpPublicationThroughTheDefinitiveReaders(): void
    {
        $publicationId = (int) Capsule::table('publications as p')
            ->join('publication_settings as ps', 'ps.publication_id', '=', 'p.publication_id')
            ->where('ps.setting_name', 'title')
            ->value('p.publication_id');
        if ($publicationId === 0) {
            $this->markTestSkipped('The OMP integration dataset has no publication with a title');
        }

        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $publication = $publicationDao->getById($publicationId);
        $this->assertNotNull($publication, 'The OMP dataset must contain the selected publication');

        $submission = $submissionDao->getById((int) $publication->getData('submissionId'));
        $this->assertNotNull($submission, 'The publication must belong to a submission');

        $titles = (new PkpLocalizedMetadataReader())->titles(
            $publication,
            $submission->getData('locale')
        );
        $work = (new PkpWorkMetadataReader(
            $submissionDao,
            $publicationDao,
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

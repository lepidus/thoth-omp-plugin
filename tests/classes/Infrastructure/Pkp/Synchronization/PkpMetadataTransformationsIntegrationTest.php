<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Synchronization;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\core\Application;
use APP\core\PageRouter;
use APP\facades\Repo;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Localized\PkpLocalizedMetadataReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Works\PkpWorkMetadataReader;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\tests\PKPTestCase;
use ReflectionClass;
use ThothApi\GraphQL\Enums\LocaleCode;

final class PkpMetadataTransformationsIntegrationTest extends PKPTestCase
{
    private $previousRouter;

    protected function setUp(): void
    {
        parent::setUp();
        $request = Application::get()->getRequest();
        $this->previousRouter = $request->getRouter();
        if ($this->previousRouter === null) {
            $router = new PageRouter();
            $router->setApplication(Application::get());
            $router->setDispatcher(Application::get()->getDispatcher());
            $request->setRouter($router);
        }
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        try {
            DB::rollBack();
        } finally {
            Application::get()->getRequest()->setRouter($this->previousRouter);
            parent::tearDown();
        }
    }

    public function testItMapsARealOmpPublicationThroughTheDefinitiveReaders(): void
    {
        $publicationId = (int) DB::table('publications as p')
            ->join('publication_settings as ps', 'ps.publication_id', '=', 'p.publication_id')
            ->where('ps.setting_name', 'title')
            ->value('p.publication_id');
        if ($publicationId === 0) {
            $publicationId = $this->createPublicationFixture();
        }
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
        $this->assertContains($titles[0]['localeCode'], (new ReflectionClass(LocaleCode::class))->getConstants());
        $this->assertContains($work['workType'], ['MONOGRAPH', 'EDITED_BOOK']);
        $this->assertSame('FORTHCOMING', $work['workStatus']);
        $this->assertStringContainsString('/catalog/book/', $work['landingPage']);
    }

    private function createPublicationFixture(): int
    {
        $contextId = (int) DB::table('presses')->value('press_id');
        if ($contextId === 0) {
            $contextId = DB::table('presses')->insertGetId([
                'path' => 'thoth-metadata-test',
                'primary_locale' => 'en',
            ], 'press_id');
        }
        $submissionId = DB::table('submissions')->insertGetId([
            'context_id' => $contextId,
            'locale' => 'en',
            'work_type' => Submission::WORK_TYPE_AUTHORED_WORK,
        ], 'submission_id');
        $publicationId = DB::table('publications')->insertGetId([
            'submission_id' => $submissionId,
            'version' => 1,
        ], 'publication_id');
        DB::table('submissions')->where('submission_id', $submissionId)->update([
            'current_publication_id' => $publicationId,
        ]);
        DB::table('publication_settings')->insert([
            'publication_id' => $publicationId,
            'locale' => 'en',
            'setting_name' => 'title',
            'setting_value' => 'Definitive metadata transformation',
        ]);

        return $publicationId;
    }
}

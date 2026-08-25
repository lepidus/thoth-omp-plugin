<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Persistence;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\facades\Repo;
use APP\plugins\generic\thoth\classes\Domain\Publication\PublicationId;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Publication\PkpPublicationReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Work\PkpSubmissionLinkRepository;
use Illuminate\Support\Facades\DB;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\tests\PKPTestCase;

final class PkpPersistenceAdaptersTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    protected function setUp(): void
    {
        parent::setUp();
        Hook::add('Schema::get::submission', static function (string $hookName, array $args): bool {
            $schema = $args[0];
            $schema->properties->thothWorkId = (object) [
                'type' => 'string',
                'validation' => ['nullable'],
            ];

            return false;
        });
        app('schema')->get(PKPSchemaService::SCHEMA_SUBMISSION, true);
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        try {
            DB::rollBack();
        } finally {
            parent::tearDown();
        }
    }

    public function testAdaptersReadAndPersistThroughTheRealOmpRepositories(): void
    {
        $publicationId = (int) DB::table('publications')->value('publication_id');
        $publication = Repo::publication()->get($publicationId);
        $this->assertNotNull($publication);
        $submissionId = (int) $publication->getData('submissionId');
        $links = new PkpSubmissionLinkRepository(Repo::submission());

        $links->saveWorkId(new SubmissionId($submissionId), new WorkId(self::WORK_ID));
        $storedWorkId = $links->findWorkId(new SubmissionId($submissionId));
        $loadedPublication = (new PkpPublicationReader(Repo::publication()))
            ->find(new PublicationId($publicationId));
        $links->deleteWorkId(new SubmissionId($submissionId));

        $this->assertNotNull($storedWorkId);
        $this->assertSame(self::WORK_ID, $storedWorkId->toString());
        $this->assertSame($publicationId, $loadedPublication?->getId());
        $this->assertNull($links->findWorkId(new SubmissionId($submissionId)));
    }
}

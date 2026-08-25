<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.tests.PKPTestCase');
import('classes.core.Services');
import('lib.pkp.classes.services.PKPSchemaService');

final class PkpPersistenceAdaptersTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    protected function setUp(): void
    {
        parent::setUp();
        HookRegistry::register('Schema::get::submission', static function ($hookName, $args): bool {
            $schema = &$args[0];
            $schema->properties->{'thothWorkId'} = (object) [
                'type' => 'string',
                'validation' => ['nullable'],
            ];

            return false;
        });
        Services::get('schema')->get(SCHEMA_SUBMISSION, true);
        Capsule::connection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        try {
            Capsule::connection()->rollBack();
            HookRegistry::clear('Schema::get::submission');
            Services::get('schema')->get(SCHEMA_SUBMISSION, true);
        } finally {
            parent::tearDown();
        }
    }

    public function testAdaptersReadAndPersistThroughTheRealOmpDaos(): void
    {
        $publicationId = (int) Capsule::table('publications')->value('publication_id');
        $this->assertGreaterThan(0, $publicationId, 'The OMP 3.3 dataset must contain a publication');
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $publication = $publicationDao->getById($publicationId);
        $this->assertNotNull($publication);
        $submissionId = (int) $publication->getData('submissionId');
        $links = new PkpSubmissionLinkRepository($submissionDao);

        $links->saveWorkId(new SubmissionId($submissionId), new WorkId(self::WORK_ID));
        $storedSetting = Capsule::table('submission_settings')
            ->where('submission_id', $submissionId)
            ->where('setting_name', 'thothWorkId')
            ->value('setting_value');
        $loadedPublication = (new PkpPublicationReader($publicationDao))
            ->find(new PublicationId($publicationId));
        $links->deleteWorkId(new SubmissionId($submissionId));

        $this->assertSame(self::WORK_ID, $storedSetting);
        $this->assertSame($publicationId, $loadedPublication->getId());
        $this->assertFalse(
            Capsule::table('submission_settings')
                ->where('submission_id', $submissionId)
                ->where('setting_name', 'thothWorkId')
                ->exists()
        );
    }

    public function testCacheAdaptersFlushTheRealPkpFileCaches(): void
    {
        import('lib.pkp.classes.cache.CacheManager');
        $cacheManager = CacheManager::getManager();
        $catalogCache = $cacheManager->getFileCache(
            'thothCatalogFiles',
            'publication-987654321',
            static fn () => null
        );
        $videoCache = $cacheManager->getFileCache(
            'thothFeatureVideo',
            'work-' . self::WORK_ID,
            static fn () => null
        );
        $catalogCache->setEntireCache(['catalogFiles' => ['sentinel']]);
        $videoCache->setEntireCache(['video' => 'sentinel']);

        try {
            $this->assertNotNull($catalogCache->getCacheTime());
            $this->assertNotNull($videoCache->getCacheTime());

            (new PkpCatalogFileCache($cacheManager))->flush(987654321);
            (new PkpFeatureVideoCache($cacheManager))->flush(new WorkId(self::WORK_ID));

            $this->assertNull($catalogCache->getCacheTime());
            $this->assertNull($videoCache->getCacheTime());
        } finally {
            $catalogCache->flush();
            $videoCache->flush();
        }
    }
}

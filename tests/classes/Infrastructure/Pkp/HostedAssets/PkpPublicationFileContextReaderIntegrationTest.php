<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\HostedAssets;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpPublicationFileContextReader;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\tests\PKPTestCase;

final class PkpPublicationFileContextReaderIntegrationTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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

    public function testItReadsAnExistingPublicationFormatThroughTheRealOmpDaos(): void
    {
        $format = DB::table('publication_formats')
            ->select(['publication_format_id', 'publication_id'])
            ->first();
        if ($format === null) {
            $format = $this->createPublicationFormatFixture();
        }
        $reader = new PkpPublicationFileContextReader(
            DAORegistry::getDAO('ChapterDAO'),
            DAORegistry::getDAO('PublicationFormatDAO')
        );

        $context = $reader->read(
            (int) $format->publication_id,
            (int) $format->publication_format_id,
            (int) $format->publication_id
        );

        $this->assertNull($context['chapterDoi']);
        $this->assertContains($context['publication']['publicationType'], [
            'PAPERBACK',
            'HARDBACK',
            'PDF',
            'HTML',
            'XML',
            'EPUB',
            'MOBI',
            'AZW3',
            'DOCX',
            'FICTION_BOOK',
            'MP3',
            'WAV',
        ]);
        $this->assertArrayHasKey('isbn', $context['publication']);
        $this->assertArrayHasKey('accessibilityStandard', $context['publication']);
    }

    private function createPublicationFormatFixture(): object
    {
        $contextId = (int) DB::table('presses')->value('press_id');
        if ($contextId === 0) {
            $contextId = DB::table('presses')->insertGetId([
                'path' => 'thoth-publication-file-test',
                'primary_locale' => 'en',
            ], 'press_id');
        }
        $submissionId = DB::table('submissions')->insertGetId([
            'context_id' => $contextId,
            'locale' => 'en',
        ], 'submission_id');
        $publicationId = DB::table('publications')->insertGetId([
            'submission_id' => $submissionId,
        ], 'publication_id');
        DB::table('submissions')->where('submission_id', $submissionId)->update([
            'current_publication_id' => $publicationId,
        ]);
        $formatId = DB::table('publication_formats')->insertGetId([
            'publication_id' => $publicationId,
            'entry_key' => 'DA',
        ], 'publication_format_id');

        return (object) [
            'publication_format_id' => $formatId,
            'publication_id' => $publicationId,
        ];
    }
}

<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\HostedAssets;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpPublicationFileContextReader;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\tests\PKPTestCase;

final class PkpPublicationFileContextReaderIntegrationTest extends PKPTestCase
{
    public function testItReadsAnExistingPublicationFormatThroughTheRealOmpDaos(): void
    {
        $format = DB::table('publication_formats')
            ->select(['publication_format_id', 'publication_id'])
            ->first();
        $this->assertNotNull($format, 'The OMP dataset must contain a publication format');
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
}

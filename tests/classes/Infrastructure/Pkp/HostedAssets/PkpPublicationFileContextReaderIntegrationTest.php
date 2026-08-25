<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use ThothApi\GraphQL\Enums\PublicationType;

import('lib.pkp.tests.PKPTestCase');

final class PkpPublicationFileContextReaderIntegrationTest extends PKPTestCase
{
    public function testItReadsAnExistingPublicationFormatThroughTheRealOmpDaos(): void
    {
        $formatDao = DAORegistry::getDAO('PublicationFormatDAO');
        $row = $formatDao->retrieve(
            'SELECT publication_format_id, publication_id FROM publication_formats ORDER BY publication_format_id LIMIT 1'
        )->current();
        if ($row === null) {
            $this->markTestSkipped('The OMP integration dataset has no publication format');
        }

        $reader = new PkpPublicationFileContextReader(
            DAORegistry::getDAO('ChapterDAO'),
            $formatDao
        );
        $context = $reader->read(
            (int) $row->publication_id,
            (int) $row->publication_format_id,
            (int) $row->publication_id
        );

        $this->assertNull($context['chapterDoi']);
        $this->assertContains($context['publication']['publicationType'], [
            PublicationType::PAPERBACK,
            PublicationType::HARDBACK,
            PublicationType::PDF,
            PublicationType::HTML,
            PublicationType::XML,
            PublicationType::EPUB,
            PublicationType::MOBI,
            PublicationType::AZW3,
            PublicationType::DOCX,
            PublicationType::FICTION_BOOK,
            PublicationType::MP3,
            PublicationType::WAV,
        ]);
        $this->assertArrayHasKey('isbn', $context['publication']);
        $this->assertArrayHasKey('accessibilityStandard', $context['publication']);
    }
}

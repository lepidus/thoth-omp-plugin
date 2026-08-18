<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Legacy;

require_once(__DIR__ . '/../../../../vendor/autoload.php');

import('plugins.generic.thoth.classes.services.ThothBookService');
import('plugins.generic.thoth.classes.services.ThothLanguageService');
import('plugins.generic.thoth.classes.services.ThothPublicationService');
import('plugins.generic.thoth.classes.services.ThothReferenceService');
import('plugins.generic.thoth.classes.services.ThothSubjectService');
import('plugins.generic.thoth.classes.services.ThothWorkRelationService');

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyLanguageSynchronizer;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyPublicationSynchronizer;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyReferenceSynchronizer;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacySubjectSynchronizer;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyWorkRelationSynchronizer;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyWorkSynchronizer;
use PKP\tests\PKPTestCase;
use stdClass;

class LegacyDomainSynchronizerTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';
    private const DELETION_WARNING =
        'plugins.generic.thoth.synchronize.activeWorkPublicationDeletionsSkipped';

    public function testWorkSynchronizerTranslatesTheLegacyWarning(): void
    {
        $publication = new stdClass();
        $service = $this->createMock(\ThothBookService::class);
        $service->expects($this->once())->method('update')
            ->with($publication, self::WORK_ID, true)->willReturn('book.warning');

        $result = (new LegacyWorkSynchronizer($service))->synchronize($publication, $this->workId());

        $this->assertSame('book.warning', $result->getWarnings()[0]->getMessageKey());
    }

    public function testPublicationSynchronizerTranslatesSkippedDeletionWarning(): void
    {
        $publication = new stdClass();
        $service = $this->createMock(\ThothPublicationService::class);
        $service->expects($this->once())->method('synchronizeByPublication')
            ->with($publication, self::WORK_ID)->willReturn(true);

        $result = (new LegacyPublicationSynchronizer($service))->synchronize($publication, $this->workId());

        $this->assertSame(self::DELETION_WARNING, $result->getWarnings()[0]->getMessageKey());
    }

    public function testLanguageSynchronizerDelegatesWithoutWarnings(): void
    {
        $publication = new stdClass();
        $service = $this->createMock(\ThothLanguageService::class);
        $service->expects($this->once())->method('synchronizeByPublication')->with($publication, self::WORK_ID);

        $this->assertEmptyResult(
            (new LegacyLanguageSynchronizer($service))->synchronize($publication, $this->workId())
        );
    }

    public function testSubjectSynchronizerDelegatesWithoutWarnings(): void
    {
        $publication = new stdClass();
        $service = $this->createMock(\ThothSubjectService::class);
        $service->expects($this->once())->method('synchronizeByPublication')->with($publication, self::WORK_ID);

        $this->assertEmptyResult(
            (new LegacySubjectSynchronizer($service))->synchronize($publication, $this->workId())
        );
    }

    public function testReferenceSynchronizerDelegatesWithoutWarnings(): void
    {
        $publication = new stdClass();
        $service = $this->createMock(\ThothReferenceService::class);
        $service->expects($this->once())->method('synchronizeByPublication')->with($publication, self::WORK_ID);

        $this->assertEmptyResult(
            (new LegacyReferenceSynchronizer($service))->synchronize($publication, $this->workId())
        );
    }

    public function testWorkRelationSynchronizerTranslatesSkippedDeletionWarning(): void
    {
        $publication = new stdClass();
        $service = $this->createMock(\ThothWorkRelationService::class);
        $service->expects($this->once())->method('synchronizeByPublication')
            ->with($publication, self::WORK_ID)->willReturn(true);

        $result = (new LegacyWorkRelationSynchronizer($service))->synchronize($publication, $this->workId());

        $this->assertSame(self::DELETION_WARNING, $result->getWarnings()[0]->getMessageKey());
    }

    private function workId(): WorkId
    {
        return new WorkId(self::WORK_ID);
    }

    private function assertEmptyResult(SynchronizationResult $result): void
    {
        $this->assertFalse($result->hasWarnings());
    }
}

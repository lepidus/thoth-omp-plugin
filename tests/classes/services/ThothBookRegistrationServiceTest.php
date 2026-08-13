<?php

require_once(__DIR__ . '/../../../vendor/autoload.php');
/**
 * @file plugins/generic/thoth/tests/classes/services/ThothBookRegistrationServiceTest.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothBookRegistrationServiceTest
 *
 * @ingroup plugins_generic_thoth_tests
 *
 * @see ThothBookRegistrationService
 *
 * @brief Test class for the ThothBookRegistrationService class
 */

use APP\plugins\generic\thoth\classes\Application\Exception\RegistrationFailed;
use APP\plugins\generic\thoth\classes\Domain\Identifier\ImprintId;
use ThothApi\Exception\QueryException;
use ThothApi\GraphQL\Client as ThothClient;
use ThothApi\GraphQL\Enums\WorkStatus;
use ThothApi\GraphQL\Inputs\PatchWork as ThothWork;

import('classes.publication.Publication');
import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.factories.ThothBookFactory');
import('plugins.generic.thoth.classes.repositories.ThothBookRepository');
import('plugins.generic.thoth.classes.services.ThothAbstractService');
import('plugins.generic.thoth.classes.services.ThothBookRegistrationService');
import('plugins.generic.thoth.classes.services.ThothContributionService');
import('plugins.generic.thoth.classes.services.ThothFrontcoverService');
import('plugins.generic.thoth.classes.services.ThothLanguageService');
import('plugins.generic.thoth.classes.services.ThothPublicationService');
import('plugins.generic.thoth.classes.services.ThothReferenceService');
import('plugins.generic.thoth.classes.services.ThothSubjectService');
import('plugins.generic.thoth.classes.services.ThothTitleService');
import('plugins.generic.thoth.classes.services.ThothWorkRelationService');

class ThothBookRegistrationServiceTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testRegisterBookMetadataAndRelations()
    {
        $mockPublication = $this->getMockBuilder(Publication::class)
            ->setMethods(['getData'])
            ->getMock();
        $mockPublication->expects($this->exactly(2))
            ->method('getData')
            ->with('locale')
            ->willReturn('en_US');

        $mockFactory = $this->getMockBuilder(ThothBookFactory::class)
            ->setMethods(['createFromPublication'])
            ->getMock();
        $mockFactory->expects($this->once())
            ->method('createFromPublication')
            ->with($mockPublication)
            ->willReturn(new ThothWork());

        $mockRepository = $this->getMockBuilder(ThothBookRepository::class)
            ->setConstructorArgs([$this->getMockBuilder(ThothClient::class)->getMock()])
            ->setMethods(['add'])
            ->getMock();
        $mockRepository->expects($this->once())
            ->method('add')
            ->with($this->isInstanceOf(ThothWork::class))
            ->willReturn('d8fa2e63-5513-45e5-84c1-e9c2d89f99d3');

        $mockAbstractService = $this->createMock(ThothAbstractService::class);
        $mockAbstractService->expects($this->once())->method('registerByPublication');

        $mockContributionService = $this->createMock(ThothContributionService::class);
        $mockContributionService->expects($this->once())->method('registerByPublication');

        $mockPublicationService = $this->createMock(ThothPublicationService::class);
        $mockPublicationService->expects($this->once())->method('registerByPublication');

        $mockLanguageService = $this->createMock(ThothLanguageService::class);
        $mockLanguageService->expects($this->once())->method('registerByPublication');

        $mockSubjectService = $this->createMock(ThothSubjectService::class);
        $mockSubjectService->expects($this->once())->method('registerByPublication');

        $mockReferenceService = $this->createMock(ThothReferenceService::class);
        $mockReferenceService->expects($this->once())->method('registerByPublication');

        $mockTitleService = $this->createMock(ThothTitleService::class);
        $mockTitleService->expects($this->once())->method('registerByPublication');

        $mockWorkRelationService = $this->createMock(ThothWorkRelationService::class);
        $mockWorkRelationService->expects($this->once())->method('registerByPublication');

        $mockFrontcoverService = $this->createMock(ThothFrontcoverService::class);
        $mockFrontcoverService->expects($this->once())
            ->method('sync')
            ->with($mockPublication, 'd8fa2e63-5513-45e5-84c1-e9c2d89f99d3')
            ->willReturn('plugins.generic.thoth.frontcover.unsupportedFormat');

        $service = new ThothBookRegistrationService(
            $mockFactory,
            $mockRepository,
            $mockAbstractService,
            $mockContributionService,
            $mockLanguageService,
            $mockPublicationService,
            $mockReferenceService,
            $mockSubjectService,
            $mockTitleService,
            $mockWorkRelationService,
            $mockFrontcoverService
        );

        $registrationResult = $service->register(
            $mockPublication,
            new ImprintId('f740cf4e-16d1-487c-9a92-615882a591e9')
        );

        $this->assertSame(
            'd8fa2e63-5513-45e5-84c1-e9c2d89f99d3',
            $registrationResult->getWorkId()->toString()
        );
        $this->assertSame(
            'plugins.generic.thoth.frontcover.unsupportedFormat',
            $registrationResult->getSynchronizationResult()->getWarnings()[0]->getMessageKey()
        );
    }

    public function testActiveBookIsRegisteredAndRemainsForthcoming()
    {
        $book = new ThothWork();
        $book->setWorkStatus(WorkStatus::ACTIVE);

        $publication = $this->getMockBuilder(Publication::class)
            ->setMethods(['getData', 'setData'])
            ->getMock();
        $publication->method('getData')->with('locale')->willReturn('en_US');
        $publication->expects($this->once())
            ->method('setData')
            ->with('thothBookId', self::WORK_ID);

        $factory = $this->getMockBuilder(ThothBookFactory::class)
            ->setMethods(['createFromPublication'])
            ->getMock();
        $factory->method('createFromPublication')->with($publication)->willReturn($book);

        $repository = $this->getMockBuilder(ThothBookRepository::class)
            ->setConstructorArgs([$this->createMock(ThothClient::class)])
            ->setMethods(['add', 'edit'])
            ->getMock();
        $repository->expects($this->once())
            ->method('add')
            ->willReturnCallback(function (ThothWork $createdBook) {
                $this->assertSame(WorkStatus::FORTHCOMING, $createdBook->getWorkStatus());
                return self::WORK_ID;
            });
        $repository->expects($this->never())->method('edit');

        $service = $this->createServiceWithDefaults($factory, $repository);

        $result = $service->register(
            $publication,
            new ImprintId('f740cf4e-16d1-487c-9a92-615882a591e9')
        );

        $this->assertSame(self::WORK_ID, $result->getWorkId()->toString());
        $this->assertSame(WorkStatus::FORTHCOMING, $book->getWorkStatus());
    }

    public function testForthcomingBookIsNotActivated()
    {
        $book = new ThothWork();
        $book->setWorkStatus(WorkStatus::FORTHCOMING);

        $publication = $this->getMockBuilder(Publication::class)
            ->setMethods(['getData', 'setData'])
            ->getMock();
        $publication->method('getData')->with('locale')->willReturn('en_US');

        $factory = $this->getMockBuilder(ThothBookFactory::class)
            ->setMethods(['createFromPublication'])
            ->getMock();
        $factory->method('createFromPublication')->willReturn($book);

        $repository = $this->getMockBuilder(ThothBookRepository::class)
            ->setConstructorArgs([$this->createMock(ThothClient::class)])
            ->setMethods(['add', 'edit'])
            ->getMock();
        $repository->expects($this->once())
            ->method('add')
            ->with($this->identicalTo($book))
            ->willReturn(self::WORK_ID);
        $repository->expects($this->never())->method('edit');

        $service = $this->createServiceWithDefaults($factory, $repository);

        $result = $service->register(
            $publication,
            new ImprintId('f740cf4e-16d1-487c-9a92-615882a591e9')
        );

        $this->assertSame(self::WORK_ID, $result->getWorkId()->toString());
        $this->assertSame(WorkStatus::FORTHCOMING, $book->getWorkStatus());
    }

    public function testRegistrationLeavesCompensationToTheUseCaseWhenMetadataFails()
    {
        $publication = $this->getMockBuilder(Publication::class)
            ->setMethods(['getData', 'setData'])
            ->getMock();
        $publication->method('getData')->with('locale')->willReturn('en_US');

        $factory = $this->getMockBuilder(ThothBookFactory::class)
            ->setMethods(['createFromPublication'])
            ->getMock();
        $factory->method('createFromPublication')->willReturn(new ThothWork());

        $repository = $this->getMockBuilder(ThothBookRepository::class)
            ->setConstructorArgs([$this->createMock(ThothClient::class)])
            ->setMethods(['add', 'delete'])
            ->getMock();
        $repository->method('add')->willReturn('work-id');
        $repository->expects($this->never())->method('delete');

        $titleService = $this->createMock(ThothTitleService::class);
        $titleService->method('registerByPublication')->willThrowException(
            new QueryException(['message' => 'metadata failed'], null, null, null, 200)
        );
        $service = $this->createServiceWithDefaults($factory, $repository, $titleService);

        $this->expectException(RegistrationFailed::class);

        $service->register(
            $publication,
            new ImprintId('f740cf4e-16d1-487c-9a92-615882a591e9')
        );
    }

    public function testRollbackDeletesTheCreatedWorkOnlyOnce()
    {
        $publication = $this->getMockBuilder(Publication::class)
            ->setMethods(['getData', 'setData'])
            ->getMock();
        $publication->expects($this->exactly(2))
            ->method('getData')
            ->with('thothBookId')
            ->willReturnOnConsecutiveCalls('work-id', null);
        $publication->expects($this->once())->method('setData')->with('thothBookId', null);
        $repository = $this->getMockBuilder(ThothBookRepository::class)
            ->setConstructorArgs([$this->createMock(ThothClient::class)])
            ->setMethods(['delete'])
            ->getMock();
        $repository->expects($this->once())->method('delete')->with('work-id');
        $service = $this->createServiceWithDefaults(
            $this->createMock(ThothBookFactory::class),
            $repository
        );

        $service->rollback($publication);
        $service->rollback($publication);
    }

    private function createServiceWithDefaults($factory, $repository, $titleService = null)
    {
        return new ThothBookRegistrationService(
            $factory,
            $repository,
            $this->createMock(ThothAbstractService::class),
            $this->createMock(ThothContributionService::class),
            $this->createMock(ThothLanguageService::class),
            $this->createMock(ThothPublicationService::class),
            $this->createMock(ThothReferenceService::class),
            $this->createMock(ThothSubjectService::class),
            $titleService ?: $this->createMock(ThothTitleService::class),
            $this->createMock(ThothWorkRelationService::class)
        );
    }
}

<?php

/**
 * @file plugins/generic/thoth/tests/classes/services/ThothChapterServiceTest.php
 *
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothChapterServiceTest
 * @ingroup plugins_generic_thoth_tests
 * @see ThothChapterService
 *
 * @brief Test class for the ThothChapterService class
 */

use ThothApi\GraphQL\Client as ThothClient;
use ThothApi\GraphQL\Models\Work as ThothWork;

import('classes.monograph.Chapter');
import('classes.publication.Publication');
import('classes.publication.PublicationDAO');
import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.factories.ThothChapterFactory');
import('plugins.generic.thoth.classes.repositories.ThothAbstractRepository');
import('plugins.generic.thoth.classes.repositories.ThothChapterRepository');
import('plugins.generic.thoth.classes.repositories.ThothTitleRepository');
import('plugins.generic.thoth.classes.services.ThothChapterService');

class ThothChapterServiceTest extends PKPTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $container = ThothContainer::getInstance();
        $this->backups = [
            'client' => $container->backup('client'),
            'abstractRepository' => $container->backup('abstractRepository'),
            'titleRepository' => $container->backup('titleRepository'),
        ];
    }

    protected function tearDown(): void
    {
        $container = ThothContainer::getInstance();
        foreach ($this->backups as $key => $factory) {
            $container->set($key, $factory);
        }
        parent::tearDown();
    }

    protected function getMockedDAOs()
    {
        return ['PublicationDAO'];
    }

    public function testRegisterChapter()
    {
        ThothContainer::getInstance()->set('client', function () {
            return $this->getMockBuilder(ThothClient::class)->getMock();
        });

        $mockAbstractRepository = $this->getMockBuilder(ThothAbstractRepository::class)
            ->setConstructorArgs([$this->getMockBuilder(ThothClient::class)->getMock()])
            ->setMethods(['new', 'add'])
            ->getMock();
        $mockAbstractRepository->method('new')->willReturnSelf();
        $mockAbstractRepository->expects($this->once())->method('add');
        ThothContainer::getInstance()->set('abstractRepository', fn () => $mockAbstractRepository);

        $mockPublication = $this->getMockBuilder(Publication::class)
            ->setMethods(['getData'])
            ->getMock();
        $mockPublication->expects($this->any())
            ->method('getData')
            ->will($this->returnValueMap([
                ['submissionId', null, 99],
                ['locale', null, 'en_US'],
            ]));

        $mockPublicationDao = $this->getMockBuilder(PublicationDAO::class)
            ->setMethods(['getById'])
            ->getMock();
        $mockPublicationDao->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($mockPublication));
        DAORegistry::registerDAO('PublicationDAO', $mockPublicationDao);

        $mockFactory = $this->getMockBuilder(ThothChapterFactory::class)
            ->setMethods(['createFromChapter'])
            ->getMock();
        $mockFactory->expects($this->once())
            ->method('createFromChapter')
            ->will($this->returnValue(new ThothWork()));

        $mockRepository = $this->getMockBuilder(ThothChapterRepository::class)
            ->setConstructorArgs([$this->getMockBuilder(ThothClient::class)->getMock()])
            ->setMethods(['add'])
            ->getMock();
        $mockRepository->expects($this->once())
            ->method('add')
            ->will($this->returnValue('fed8b9ee-2537-4a66-a1a1-eeadf4001c59'));

        $mockTitleRepository = $this->getMockBuilder(ThothTitleRepository::class)
            ->setConstructorArgs([$this->getMockBuilder(ThothClient::class)->getMock()])
            ->setMethods(['new', 'add'])
            ->getMock();
        $mockTitleRepository->method('new')->willReturnSelf();
        $mockTitleRepository->expects($this->once())->method('add');
        ThothContainer::getInstance()->set('titleRepository', fn () => $mockTitleRepository);

        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['toArray'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockResult->expects($this->once())
            ->method('toArray')
            ->will($this->returnValue([]));

        $mockChapter = $this->getMockBuilder(Chapter::class)
            ->setMethods(['getAuthors', 'getData', 'getLocalizedFullTitle', 'getLocalizedTitle', 'getLocalizedData'])
            ->getMock();
        $mockChapter->expects($this->once())
            ->method('getAuthors')
            ->will($this->returnValue($mockResult));
        $mockChapter->expects($this->any())
            ->method('getData')
            ->will($this->returnValueMap([
                ['publicationId', null, 99],
                ['thothChapterId', null, 'a518bebb-4a2c-48bb-8781-071ece2f2745']
            ]));
        $mockChapter->method('getLocalizedFullTitle')->will($this->returnValue('My chapter title: My chapter subtitle'));
        $mockChapter->method('getLocalizedTitle')->will($this->returnValue('My chapter title'));
        $mockChapter->method('getLocalizedData')->will($this->returnCallback(function ($key) {
            $values = [
                'subtitle' => 'My chapter subtitle',
                'abstract' => 'This is my chapter abstract',
            ];

            return $values[$key] ?? null;
        }));

        $thothImprintId = 'd7991bfa-0ed3-432f-b9bd-0c7d0a4a1dec';

        $service = new ThothChapterService($mockFactory, $mockRepository);
        $thothChapterId = $service->register($mockChapter, $thothImprintId);

        $this->assertSame('fed8b9ee-2537-4a66-a1a1-eeadf4001c59', $thothChapterId);
    }
}

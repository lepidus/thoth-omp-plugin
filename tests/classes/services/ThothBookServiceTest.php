<?php

/**
 * @file plugins/generic/thoth/tests/classes/services/ThothBookServiceTest.php
 *
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothBookServiceTest
 *
 * @ingroup plugins_generic_thoth_tests
 *
 * @see ThothBookService
 *
 * @brief Test class for the ThothBookService class
 */

use PKP\tests\PKPTestCase;
use ThothApi\GraphQL\Client as ThothClient;
use ThothApi\GraphQL\Models\Work as ThothWork;

import('plugins.generic.thoth.classes.factories.ThothBookFactory');
import('plugins.generic.thoth.classes.repositories.ThothAbstractRepository');
import('plugins.generic.thoth.classes.repositories.ThothBookRepository');
import('plugins.generic.thoth.classes.repositories.ThothTitleRepository');
import('plugins.generic.thoth.classes.services.ThothBookService');
import('plugins.generic.thoth.classes.services.ThothContributionService');
import('plugins.generic.thoth.classes.services.ThothLanguageService');
import('plugins.generic.thoth.classes.services.ThothPublicationService');
import('plugins.generic.thoth.classes.services.ThothReferenceService');
import('plugins.generic.thoth.classes.services.ThothSubjectService');
import('plugins.generic.thoth.classes.services.ThothWorkRelationService');

class ThothBookServiceTest extends PKPTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $container = ThothContainer::getInstance();
        $this->backups = [
            'client' => $container->backup('client'),
            'abstractRepository' => $container->backup('abstractRepository'),
            'contributionService' => $container->backup('contributionService'),
            'languageService' => $container->backup('languageService'),
            'publicationService' => $container->backup('publicationService'),
            'referenceService' => $container->backup('referenceService'),
            'subjectService' => $container->backup('subjectService'),
            'titleRepository' => $container->backup('titleRepository'),
            'workRelationService' => $container->backup('workRelationService'),
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

    public function testRegisterBook()
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

        $mockContributionService = $this->createMock(ThothContributionService::class);
        $mockContributionService->method('registerByPublication');
        ThothContainer::getInstance()->set('contributionService', fn () => $mockContributionService);

        $mockPublicationService = $this->createMock(ThothPublicationService::class);
        $mockPublicationService->method('registerByPublication');
        ThothContainer::getInstance()->set('publicationService', fn () => $mockPublicationService);

        $mockLanguageService = $this->createMock(ThothLanguageService::class);
        $mockLanguageService->method('registerByPublication');
        ThothContainer::getInstance()->set('languageService', fn () => $mockLanguageService);

        $mockSubjectService = $this->createMock(ThothSubjectService::class);
        $mockSubjectService->method('registerByPublication');
        ThothContainer::getInstance()->set('subjectService', fn () => $mockSubjectService);

        $mockReferenceService = $this->createMock(ThothReferenceService::class);
        $mockReferenceService->method('registerByPublication');
        ThothContainer::getInstance()->set('referenceService', fn () => $mockReferenceService);

        $mockFactory = $this->getMockBuilder(ThothBookFactory::class)
            ->setMethods(['createFromPublication'])
            ->getMock();
        $mockFactory->expects($this->once())
            ->method('createFromPublication')
            ->will($this->returnValue(new ThothWork()));

        $mockRepository = $this->getMockBuilder(ThothBookRepository::class)
            ->setConstructorArgs([$this->getMockBuilder(ThothClient::class)->getMock()])
            ->setMethods(['add'])
            ->getMock();
        $mockRepository->expects($this->once())
            ->method('add')
            ->will($this->returnValue('d8fa2e63-5513-45e5-84c1-e9c2d89f99d3'));

        $mockTitleRepository = $this->getMockBuilder(ThothTitleRepository::class)
            ->setConstructorArgs([$this->getMockBuilder(ThothClient::class)->getMock()])
            ->setMethods(['new', 'add'])
            ->getMock();
        $mockTitleRepository->method('new')->willReturnSelf();
        $mockTitleRepository->expects($this->once())->method('add');
        ThothContainer::getInstance()->set('titleRepository', fn () => $mockTitleRepository);

        $mockWorkRelationService = $this->createMock(ThothWorkRelationService::class);
        $mockWorkRelationService->method('registerByPublication');
        ThothContainer::getInstance()->set('workRelationService', fn () => $mockWorkRelationService);

        $mockPublication = $this->getMockBuilder(\APP\publication\Publication::class)
            ->setMethods(['getData', 'getLocalizedFullTitle', 'getLocalizedTitle', 'getLocalizedData'])
            ->getMock();
        $mockPublication->expects($this->any())
            ->method('getData')
            ->will($this->returnCallback(function ($key) {
                $values = [
                    'locale' => 'en_US',
                ];

                return $values[$key] ?? null;
            }));
        $mockPublication->method('getLocalizedFullTitle')->will($this->returnValue('My book title: My book subtitle'));
        $mockPublication->method('getLocalizedTitle')->will($this->returnValue('My book title'));
        $mockPublication->method('getLocalizedData')->will($this->returnCallback(function ($key) {
            $values = [
                'subtitle' => 'My book subtitle',
                'abstract' => 'This is my book abstract',
            ];

            return $values[$key] ?? null;
        }));

        $thothImprintId = 'f740cf4e-16d1-487c-9a92-615882a591e9';

        $service = new ThothBookService($mockFactory, $mockRepository);
        $thothBookId = $service->register($mockPublication, $thothImprintId);

        $this->assertSame('d8fa2e63-5513-45e5-84c1-e9c2d89f99d3', $thothBookId);
    }

    public function testDoiExistsBookValidationFails()
    {
        $mockFactory = $this->getMockBuilder(ThothBookFactory::class)
            ->setMethods(['createFromPublication'])
            ->getMock();
        $mockFactory->expects($this->once())
            ->method('createFromPublication')
            ->will($this->returnValue(new ThothWork([
                'doi' => 'https://doi.org/10.12345/10101010'
            ])));

        $mockRepository = $this->getMockBuilder(ThothBookRepository::class)
            ->setConstructorArgs([$this->getMockBuilder(ThothClient::class)->getMock()])
            ->setMethods(['getByDoi'])
            ->getMock();
        $mockRepository->expects($this->once())
            ->method('getByDoi')
            ->will($this->returnValue(new ThothWork()));

        $mockPublication = $this->getMockBuilder(\APP\publication\Publication::class)->getMock();

        $service = new ThothBookService($mockFactory, $mockRepository);
        $errors = $service->validate($mockPublication);

        $this->assertEquals([
            '##plugins.generic.thoth.validation.doiExists##',
        ], $errors);
    }

    public function testLandingPageExistsBookValidationFails()
    {
        $mockFactory = $this->getMockBuilder(ThothBookFactory::class)
            ->setMethods(['createFromPublication'])
            ->getMock();
        $mockFactory->expects($this->once())
            ->method('createFromPublication')
            ->will($this->returnValue(new ThothWork([
                'landingPage' => 'http://www.publicknowledge.omp/index.php/publicknowledge/catalog/book/14'
            ])));

        $mockRepository = $this->getMockBuilder(ThothBookRepository::class)
            ->setConstructorArgs([$this->getMockBuilder(ThothClient::class)->getMock()])
            ->setMethods(['find'])
            ->getMock();
        $mockRepository->expects($this->once())
            ->method('find')
            ->will($this->returnValue(new ThothWork([
                'landingPage' => 'http://www.publicknowledge.omp/index.php/publicknowledge/catalog/book/14'
            ])));

        $mockPublication = $this->getMockBuilder(\APP\publication\Publication::class)->getMock();

        $service = new ThothBookService($mockFactory, $mockRepository);
        $errors = $service->validate($mockPublication);

        $this->assertEquals([
            '##plugins.generic.thoth.validation.landingPageExists##',
        ], $errors);
    }
}

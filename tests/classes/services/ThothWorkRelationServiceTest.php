<?php

/**
 * @file plugins/generic/thoth/tests/classes/services/ThothWorkRelationServiceTest.php
 *
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothWorkRelationServiceTest
 * @ingroup plugins_generic_thoth_tests
 * @see ThothWorkRelationService
 *
 * @brief Test class for the ThothWorkRelationService class
 */

use ThothApi\GraphQL\Client as ThothClient;
use ThothApi\GraphQL\Models\Work as ThothWork;
use ThothApi\GraphQL\Models\WorkRelation as ThothWorkRelation;

import('classes.monograph.Chapter');
import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.factories.ThothChapterFactory');
import('plugins.generic.thoth.classes.repositories.ThothWorkRelationRepository');
import('plugins.generic.thoth.classes.repositories.ThothChapterRepository');
import('plugins.generic.thoth.classes.services.ThothChapterService');
import('plugins.generic.thoth.classes.services.ThothWorkRelationService');

class ThothWorkRelationServiceTest extends PKPTestCase
{
    public function testRegisterWorkRelation()
    {
        ThothContainer::getInstance()->set('chapterService', function () {
            $mockService = $this->getMockBuilder(ThothChapterService::class)
                ->setConstructorArgs([
                    $this->getMockBuilder(ThothChapterFactory::class)->getMock(),
                    $this->getMockBuilder(ThothChapterRepository::class)
                        ->setConstructorArgs([$this->getMockBuilder(ThothClient::class)
                        ->getMock()
                    ])
                    ->getMock(),
                ])
                ->setMethods(['register'])
                ->getMock();
            $mockService->expects($this->any())
                ->method('register')
                ->will($this->returnValue('dccd9dfd-fee2-4e85-b1f8-0440f9b43ce8'));

            return $mockService;
        });

        $mockRepository = $this->getMockBuilder(ThothWorkRelationRepository::class)
            ->setConstructorArgs([$this->getMockBuilder(ThothClient::class)->getMock()])
            ->setMethods(['add'])
            ->getMock();
        $mockRepository->expects($this->once())
            ->method('add')
            ->will($this->returnValue('91966e15-0203-4eb8-b7e7-02b72c57cedc'));

        $mockChapter = $this->getMockBuilder(Chapter::class)->getMock();

        $service = new ThothWorkRelationService($mockRepository);
        $thothWorkRelationId = $service->register($mockChapter, new ThothWork([
            'imprintId' => '681fc72f-fcab-44d3-b269-5a17b53cdde4',
            'workId' => '6b7317a4-d6db-4388-b683-b5395d587377'
        ]));

        $this->assertSame('91966e15-0203-4eb8-b7e7-02b72c57cedc', $thothWorkRelationId);
    }
}

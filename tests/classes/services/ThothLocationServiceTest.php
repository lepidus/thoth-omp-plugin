<?php

/**
 * @file plugins/generic/thoth/tests/classes/services/ThothLocationServiceTest.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Copyright (c) 2024 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothLocationServiceTest
 *
 * @ingroup plugins_generic_thoth_tests
 *
 * @see ThothLocationService
 *
 * @brief Test class for the ThothLocationService class
 */

use APP\core\Application;
use PKP\core\Dispatcher;
use PKP\core\PKPRequest;
use PKP\core\Registry;
use PKP\tests\PKPTestCase;
use ThothApi\GraphQL\Models\Location as ThothLocation;

import('plugins.generic.thoth.classes.services.ThothLocationService');

class ThothLocationServiceTest extends PKPTestCase
{
    private $clientFactoryBackup;
    private $configFactoryBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientFactoryBackup = ThothContainer::getInstance()->backup('client');
        $this->locationService = new ThothLocationService();
        $this->setUpMockEnvironment();
    }

    protected function tearDown(): void
    {
        unset($this->locationService);
        ThothContainer::getInstance()->set('client', $this->clientFactoryBackup);
        parent::tearDown();
    }

    protected function getMockedRegistryKeys(): array
    {
        return ['application', 'request'];
    }

    private function setUpMockEnvironment()
    {
        $pressMock = Mockery::mock(\APP\press\Press::class)
            ->makePartial()
            ->shouldReceive([
                'getId' => 2,
                'getPrimaryLocale' => 'en',
                'getPath' => 'press'
            ])
            ->getMock();

        $mockApplication = $this->getMockBuilder(Application::class)
            ->setMethods(['getContextDepth', 'getContextList'])
            ->getMock();
        $mockApplication->expects($this->any())
            ->method('getContextDepth')
            ->will($this->returnValue(1));
        $mockApplication->expects($this->any())
            ->method('getContextList')
            ->will($this->returnValue(['firstContext']));
        Registry::set('application', $mockApplication);

        $mockDispatcher = $this->getMockBuilder(Dispatcher::class)
            ->setMethods(['url'])
            ->getMock();
        $mockDispatcher->expects($this->any())
            ->method('url')
            ->will($this->onConsecutiveCalls(
                'https://omp.publicknowledgeproject.org/press/catalog/book/23',
                'https://omp.publicknowledgeproject.org/press/catalog/view/23/5/17'
            ));

        $mockRequest = $this->getMockBuilder(PKPRequest::class)
            ->setMethods(['getContext', 'url'])
            ->getMock();
        $mockRequest->setDispatcher($mockDispatcher);
        $mockRequest->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($pressMock));
        Registry::set('request', $mockRequest);
    }

    public function testCreateNewLocationByPublicationFormat()
    {
        $expectedLocation = new ThothLocation();
        $expectedLocation->setLandingPage('https://omp.publicknowledgeproject.org/press/catalog/book/23');
        $expectedLocation->setFullTextUrl('https://omp.publicknowledgeproject.org/press/catalog/view/23/5/17');
        $expectedLocation->setLocationPlatform(ThothLocation::LOCATION_PLATFORM_OTHER);

        $publicationFormatMock = Mockery::mock(\APP\publicationFormat\PublicationFormat::class)
            ->makePartial()
            ->shouldReceive('getData')
            ->with('publicationId')
            ->andReturn(1)
            ->getMock();

        $location = $this->locationService->newByPublicationFormat($publicationFormatMock, 17);

        $this->assertEquals($expectedLocation, $location);
    }

    public function testCreateNewLocation()
    {
        $expectedLocation = new ThothLocation();
        $expectedLocation->setLandingPage('https://omp.publicknowledgeproject.org/press/catalog/book/12');
        $expectedLocation->setFullTextUrl('https://www.bookstore.com/site/books/book34');
        $expectedLocation->setLocationPlatform(ThothLocation::LOCATION_PLATFORM_OTHER);

        $location = $this->locationService->new([
            'landingPage' => 'https://omp.publicknowledgeproject.org/press/catalog/book/12',
            'fullTextUrl' => 'https://www.bookstore.com/site/books/book34',
            'locationPlatform' => ThothLocation::LOCATION_PLATFORM_OTHER,
        ]);

        $this->assertEquals($expectedLocation, $location);
    }

    public function testRegisterLocation()
    {
        $thothPublicationId = '8ac3e585-c32a-42d7-bd36-ef42ee397e6e';

        $expectedLocation = new ThothLocation();
        $expectedLocation->setLocationId('03b0367d-bba3-4e26-846a-4c36d3920db2');
        $expectedLocation->setPublicationId($thothPublicationId);
        $expectedLocation->setLandingPage('https://omp.publicknowledgeproject.org/press/catalog/book/23');
        $expectedLocation->setFullTextUrl('https://www.bookstore.com/site/books/book5');
        $expectedLocation->setLocationPlatform(ThothLocation::LOCATION_PLATFORM_OTHER);
        $expectedLocation->setCanonical(true);

        $publicationFormatMock = Mockery::mock(\APP\publicationFormat\PublicationFormat::class)
            ->makePartial()
            ->shouldReceive('getId')
            ->withAnyArgs()
            ->andReturn(1)
            ->shouldReceive('getData')
            ->with('publicationId')
            ->andReturn(1)
            ->shouldReceive('getRemoteUrl')
            ->with()
            ->andReturn('https://www.bookstore.com/site/books/book5')
            ->getMock();

        $mockThothClient = $this->getMockBuilder(ThothClient::class)
            ->setMethods([
                'createLocation',
            ])
            ->getMock();
        $mockThothClient->expects($this->any())
            ->method('createLocation')
            ->will($this->returnValue('03b0367d-bba3-4e26-846a-4c36d3920db2'));

        ThothContainer::getInstance()->set('client', function () use ($mockThothClient) {
            return $mockThothClient;
        });


        $location = $this->locationService->register($publicationFormatMock, $thothPublicationId);
        $this->assertEquals($expectedLocation, $location);
    }
}

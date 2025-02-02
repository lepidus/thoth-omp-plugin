<?php

/**
 * @file plugins/generic/thoth/tests/classes/services/ThothPublicationServiceTest.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Copyright (c) 2024 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothPublicationServiceTest
 *
 * @ingroup plugins_generic_thoth_tests
 *
 * @see ThothPublicationService
 *
 * @brief Test class for the ThothPublicationService class
 */

use APP\publicationFormat\PublicationFormat;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\tests\PKPTestCase;
use ThothApi\GraphQL\Client as ThothClient;
use ThothApi\GraphQL\Models\Publication as ThothPublication;

import('plugins.generic.thoth.classes.services.ThothPublicationService');

class ThothPublicationServiceTest extends PKPTestCase
{
    private $clientFactoryBackup;
    private $configFactoryBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientFactoryBackup = ThothContainer::getInstance()->backup('client');
        $this->publicationService = new ThothPublicationService();
    }

    protected function tearDown(): void
    {
        unset($this->publicationService);
        ThothContainer::getInstance()->set('client', $this->clientFactoryBackup);
        parent::tearDown();
    }

    public function testGetPublicationTypeByPublicationFormat()
    {
        $publicationFormat = DAORegistry::getDAO('PublicationFormatDAO')->newDataObject();

        $publicationFormat->setEntryKey('BC');
        $this->assertEquals(
            ThothPublication::PUBLICATION_TYPE_PAPERBACK,
            $this->publicationService->getPublicationTypeByPublicationFormat($publicationFormat)
        );
        $publicationFormat->setEntryKey('BB');
        $this->assertEquals(
            ThothPublication::PUBLICATION_TYPE_HARDBACK,
            $this->publicationService->getPublicationTypeByPublicationFormat($publicationFormat)
        );
        $publicationFormat->setEntryKey('DA');
        $publicationFormat->setName('HTML', 'en_US');
        $this->assertEquals(
            ThothPublication::PUBLICATION_TYPE_HTML,
            $this->publicationService->getPublicationTypeByPublicationFormat($publicationFormat)
        );
        $publicationFormat->setEntryKey('DA');
        $publicationFormat->setName('PDF', 'en_US');
        $this->assertEquals(
            ThothPublication::PUBLICATION_TYPE_PDF,
            $this->publicationService->getPublicationTypeByPublicationFormat($publicationFormat)
        );
        $publicationFormat->setEntryKey('DA');
        $publicationFormat->setName('XML', 'en_US');
        $this->assertEquals(
            ThothPublication::PUBLICATION_TYPE_XML,
            $this->publicationService->getPublicationTypeByPublicationFormat($publicationFormat)
        );
        $publicationFormat->setEntryKey('DA');
        $publicationFormat->setName('EPUB', 'en_US');
        $this->assertEquals(
            ThothPublication::PUBLICATION_TYPE_EPUB,
            $this->publicationService->getPublicationTypeByPublicationFormat($publicationFormat)
        );
        $publicationFormat->setEntryKey('DA');
        $publicationFormat->setName('MOBI', 'en_US');
        $this->assertEquals(
            ThothPublication::PUBLICATION_TYPE_MOBI,
            $this->publicationService->getPublicationTypeByPublicationFormat($publicationFormat)
        );
        $publicationFormat->setEntryKey('DA');
        $publicationFormat->setName('AZW3', 'en_US');
        $this->assertEquals(
            ThothPublication::PUBLICATION_TYPE_AZW3,
            $this->publicationService->getPublicationTypeByPublicationFormat($publicationFormat)
        );
        $publicationFormat->setEntryKey('DA');
        $publicationFormat->setName('DOCX', 'en_US');
        $this->assertEquals(
            ThothPublication::PUBLICATION_TYPE_DOCX,
            $this->publicationService->getPublicationTypeByPublicationFormat($publicationFormat)
        );
        $publicationFormat->setEntryKey('DA');
        $publicationFormat->setName('Fiction Book', 'en_US');
        $this->assertEquals(
            ThothPublication::PUBLICATION_TYPE_FICTION_BOOK,
            $this->publicationService->getPublicationTypeByPublicationFormat($publicationFormat)
        );
    }

    public function testGetIsbnByPublicationFormat()
    {
        $identificationCode = DAORegistry::getDAO('IdentificationCodeDAO')->newDataObject();
        $identificationCode->setCode('15');
        $identificationCode->setValue('978-3-95796-140-2');

        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['toArray'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockResult->expects($this->any())
            ->method('toArray')
            ->will($this->returnValue([$identificationCode]));

        $publicationFormat = $mockRequest = $this->getMockBuilder(PublicationFormat::class)
            ->setMethods(['getIdentificationCodes'])
            ->getMock();
        $publicationFormat->expects($this->any())
            ->method('getIdentificationCodes')
            ->will($this->returnValue($mockResult));

        $this->assertEquals(
            '978-3-95796-140-2',
            $this->publicationService->getIsbnByPublicationFormat($publicationFormat)
        );
    }

    public function testCreateNewPublicationByPublicationFormat()
    {
        $expectedPublication = new ThothPublication();
        $expectedPublication->setPublicationType(ThothPublication::PUBLICATION_TYPE_PAPERBACK);
        $expectedPublication->setIsbn('978-0-615-94946-8');

        $identificationCode = DAORegistry::getDAO('IdentificationCodeDAO')->newDataObject();
        $identificationCode->setCode('15');
        $identificationCode->setValue('978-0-615-94946-8');

        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['toArray'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockResult->expects($this->any())
            ->method('toArray')
            ->will($this->returnValue([$identificationCode]));

        $publicationFormat = $mockRequest = $this->getMockBuilder(PublicationFormat::class)
            ->setMethods(['getIdentificationCodes'])
            ->getMock();
        $publicationFormat->expects($this->any())
            ->method('getIdentificationCodes')
            ->will($this->returnValue($mockResult));
        $publicationFormat->setEntryKey('BC');

        $publication = $this->publicationService->newByPublicationFormat($publicationFormat);

        $this->assertEquals($expectedPublication, $publication);
    }

    public function testCreateNewPublication()
    {
        $expectedPublication = new ThothPublication();
        $expectedPublication->setPublicationType(ThothPublication::PUBLICATION_TYPE_PDF);
        $expectedPublication->setIsbn('978-0-615-62535-5');

        $params = [
            'publicationType' => ThothPublication::PUBLICATION_TYPE_PDF,
            'isbn' => '978-0-615-62535-5',
        ];

        $publication = $this->publicationService->new($params);
        $this->assertEquals($expectedPublication, $publication);
    }

    public function testSearchPublication()
    {
        $isbn = '978-65-89999-01-3';

        $expectedThothPublications = [
            new ThothPublication([
                'isbn' => $isbn
            ])
        ];

        $mockThothClient = $this->getMockBuilder(ThothClient::class)
            ->setMethods(['publications'])
            ->getMock();
        $mockThothClient->expects($this->any())
            ->method('publications')
            ->will($this->returnValue([
                new ThothPublication([
                    'isbn' => $isbn
                ])
            ]));

        ThothContainer::getInstance()->set('client', function () use ($mockThothClient) {
            return $mockThothClient;
        });

        $thothPublications = $this->publicationService->search($isbn);

        $this->assertEquals($expectedThothPublications, $thothPublications);
    }

    public function testRegisterPublication()
    {
        $workId = '2a065323-76cd-4f54-b83b-19f2a925f426';

        $expectedPublication = new ThothPublication();
        $expectedPublication->setPublicationId('80359118-9b33-4cf4-a4b4-8784e6d4375a');
        $expectedPublication->setWorkId($workId);
        $expectedPublication->setPublicationType(ThothPublication::PUBLICATION_TYPE_HTML);
        $expectedPublication->setIsbn('978-1-912656-00-4');

        $identificationCode = DAORegistry::getDAO('IdentificationCodeDAO')->newDataObject();
        $identificationCode->setCode('15');
        $identificationCode->setValue('978-1-912656-00-4');

        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['toArray'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockResult->expects($this->any())
            ->method('toArray')
            ->will($this->returnValue([$identificationCode]));

        $publicationFormat = $mockRequest = $this->getMockBuilder(PublicationFormat::class)
            ->setMethods(['getIdentificationCodes'])
            ->getMock();
        $publicationFormat->expects($this->any())
            ->method('getIdentificationCodes')
            ->will($this->returnValue($mockResult));
        $publicationFormat->setEntryKey('DA');
        $publicationFormat->setName('HTML', 'en_US');

        $mockThothClient = $this->getMockBuilder(ThothClient::class)
            ->setMethods([
                'createPublication',
            ])
            ->getMock();
        $mockThothClient->expects($this->any())
            ->method('createPublication')
            ->will($this->returnValue('80359118-9b33-4cf4-a4b4-8784e6d4375a'));

        ThothContainer::getInstance()->set('client', function () use ($mockThothClient) {
            return $mockThothClient;
        });


        $publication = $this->publicationService->register($publicationFormat, $workId);
        $this->assertEquals($expectedPublication, $publication);
    }
}

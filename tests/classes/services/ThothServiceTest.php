<?php

/**
 * @file plugins/generic/thoth/tests/classes/services/ThothServiceTest.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Copyright (c) 2024 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothServiceTest
 * @ingroup plugins_generic_thoth_tests
 * @see ThothService
 *
 * @brief Test class for the ThothService class
 */

import('classes.core.Application');
import('classes.press.Press');
import('classes.publication.Publication');
import('classes.submission.Submission');
import('classes.monograph.Author');
import('lib.pkp.classes.core.PKPRequest');
import('lib.pkp.classes.core.PKPRouter');
import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.services.ThothService');
import('plugins.generic.thoth.thoth.models.ThothContribution');
import('plugins.generic.thoth.thoth.models.ThothContributor');
import('plugins.generic.thoth.thoth.models.ThothWork');
import('plugins.generic.thoth.thoth.models.ThothWorkRelation');
import('plugins.generic.thoth.thoth.ThothClient');
import('plugins.generic.thoth.ThothPlugin');

class ThothServiceTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->thothService = $this->setUpMockEnvironment();
    }

    protected function tearDown(): void
    {
        unset($this->thothService);
        parent::tearDown();
    }

    protected function getMockedRegistryKeys()
    {
        return ['application', 'request'];
    }

    protected function getMockedDAOs()
    {
        return ['SubmissionDAO', 'PublicationDAO'];
    }

    private function setUpMockEnvironment()
    {
        $press = new Press();
        $press->setId(2);
        $press->setPrimaryLocale('en_US');
        $press->setPath('press');

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

        $mockRequest = $this->getMockBuilder(PKPRequest::class)
            ->setMethods(['getContext', 'getBaseUrl', 'url'])
            ->getMock();
        $dispatcher = $mockApplication->getDispatcher();
        $mockRequest->setDispatcher($dispatcher);
        $mockRequest->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($press));
        $mockRequest->expects($this->any())
            ->method('getBaseUrl')
            ->will($this->returnValue('https://omp.publicknowledgeproject.org'));
        Registry::set('request', $mockRequest);

        $submissionDaoMock = $this->getMockBuilder(SubmissionDAO::class)
            ->setMethods(['getById'])
            ->getMock();
        $submission = new Submission();
        $submission->setId(53);
        $submissionDaoMock->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($submission));
        DAORegistry::registerDAO('SubmissionDAO', $submissionDaoMock);

        $publicationMockDao = $this->getMockBuilder(PublicationDAO::class)
            ->setMethods(['getById'])
            ->getMock();
        $publication = new Publication();
        $publication->setData('primaryContactId', 13);
        $publicationMockDao->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($publication));
        DAORegistry::registerDAO('PublicationDAO', $publicationMockDao);

        $mockPlugin = $this->getMockBuilder(ThothPlugin::class)
            ->setMethods(['getSetting'])
            ->getMock();
        $mockPlugin->expects($this->any())
            ->method('getSetting')
            ->willReturnMap([
                [$press->getId(), 'apiUrl', 'https://api.thoth.test.pub/'],
                [$press->getId(), 'imprintId', 'f02786d4-3bcc-473e-8d43-3da66c7e877c'],
                [$press->getId(), 'email', 'thoth@mailinator.com'],
                [$press->getId(), 'password', 'thoth']
            ]);

        $mockThothClient = $this->getMockBuilder(ThothClient::class)
            ->setMethods([
                'createWork',
                'createContributor',
                'createContribution',
                'createWorkRelation',
                'createPublication',
                'createLocation'
            ])
            ->getMock();
        $mockThothClient->expects($this->any())
            ->method('createWork')
            ->will($this->returnValue('74fde3e2-ca4e-4597-bb0c-aee90648f5a5'));
        $mockThothClient->expects($this->any())
            ->method('createContributor')
            ->will($this->returnValue('f70f709e-2137-4c87-a2e5-d52b263759ec'));
        $mockThothClient->expects($this->any())
            ->method('createContribution')
            ->will($this->returnValue('67afac83-b015-4f32-9576-60b665a9e685'));
        $mockThothClient->expects($this->any())
            ->method('createWorkRelation')
            ->will($this->returnValue('3e587b61-58f1-4064-bf80-e40e5c924d27'));
        $mockThothClient->expects($this->any())
            ->method('createPublication')
            ->will($this->returnValue('80359118-9b33-4cf4-a4b4-8784e6d4375a'));
        $mockThothClient->expects($this->any())
            ->method('createLocation')
            ->will($this->returnValue('03b0367d-bba3-4e26-846a-4c36d3920db2'));

        $thothService = $this->getMockBuilder(ThothService::class)
            ->setMethods(['getThothClient'])
            ->setConstructorArgs([$mockPlugin, $press->getId()])
            ->getMock();
        $thothService->expects($this->any())
            ->method('getThothClient')
            ->will($this->returnValue($mockThothClient));

        return $thothService;
    }

    public function testRegisterBook()
    {
        $expectedBook = new ThothWork();
        $expectedBook->setId('74fde3e2-ca4e-4597-bb0c-aee90648f5a5');
        $expectedBook->setImprintId('f02786d4-3bcc-473e-8d43-3da66c7e877c');
        $expectedBook->setWorkType(ThothWork::WORK_TYPE_MONOGRAPH);
        $expectedBook->setWorkStatus(ThothWork::WORK_STATUS_ACTIVE);
        $expectedBook->setTitle('A Designer\'s Log');
        $expectedBook->setSubtitle('Case Studies in Instructional Design');
        $expectedBook->setFullTitle('A Designer\'s Log: Case Studies in Instructional Design');
        $expectedBook->setLandingPage('https://omp.publicknowledgeproject.org/index.php/press/catalog/book/11');
        $expectedBook->setCoverUrl('https://omp.publicknowledgeproject.org/templates/images/book-default.png');

        $publication = new Publication();
        $publication->setId(12);
        $publication->setData('title', 'A Designer\'s Log', 'en_US');
        $publication->setData('subtitle', 'Case Studies in Instructional Design', 'en_US');

        $submission = new Submission();
        $submission->setData('id', 11);
        $submission->setData('locale', 'en_US');
        $submission->setData('workType', WORK_TYPE_AUTHORED_WORK);
        $submission->setData('currentPublicationId', 12);
        $submission->setData('publications', [$publication]);

        $book = $this->thothService->registerBook($submission);
        $this->assertEquals($expectedBook, $book);
    }

    public function testRegisterContributor()
    {
        $expectedContributor = new ThothContributor();
        $expectedContributor->setId('f70f709e-2137-4c87-a2e5-d52b263759ec');
        $expectedContributor->setFirstName('Brian');
        $expectedContributor->setLastName('Dupuis');
        $expectedContributor->setFullName('Brian Dupuis');

        $author = new Author();
        $author->setGivenName('Brian', 'en_US');
        $author->setFamilyName('Dupuis', 'en_US');

        $contributor = $this->thothService->registerContributor($author);
        $this->assertEquals($expectedContributor, $contributor);
    }

    public function testRegisterContribution()
    {
        $expectedContribution = new ThothContribution();
        $expectedContribution->setId('67afac83-b015-4f32-9576-60b665a9e685');
        $expectedContribution->setWorkId('45a6622c-a306-4559-bb77-25367dc881b8');
        $expectedContribution->setContributorId('f70f709e-2137-4c87-a2e5-d52b263759ec');
        $expectedContribution->setContributionType(ThothContribution::CONTRIBUTION_TYPE_AUTHOR);
        $expectedContribution->setMainContribution(true);
        $expectedContribution->setContributionOrdinal(1);
        $expectedContribution->setFirstName('Michael');
        $expectedContribution->setLastName('Wilson');
        $expectedContribution->setFullName('Michael Wilson');

        $userGroup = new UserGroup();
        $userGroup->setData('nameLocaleKey', 'default.groups.name.author');

        $author = $this->getMockBuilder(Author::class)
            ->setMethods(['getUserGroup'])
            ->getMock();
        $author->expects($this->any())
            ->method('getUserGroup')
            ->will($this->returnValue($userGroup));
        $author->setId(13);
        $author->setGivenName('Michael', 'en_US');
        $author->setFamilyName('Wilson', 'en_US');
        $author->setSequence(0);

        $contribution = $this->thothService->registerContribution($author, '45a6622c-a306-4559-bb77-25367dc881b8');
        $this->assertEquals($expectedContribution, $contribution);
    }

    public function testRegisterChapter()
    {
        $expectedChapter = new ThothWork();
        $expectedChapter->setId('74fde3e2-ca4e-4597-bb0c-aee90648f5a5');
        $expectedChapter->setImprintId('f02786d4-3bcc-473e-8d43-3da66c7e877c');
        $expectedChapter->setWorkType(ThothWork::WORK_TYPE_BOOK_CHAPTER);
        $expectedChapter->setWorkStatus(ThothWork::WORK_STATUS_ACTIVE);
        $expectedChapter->setFullTitle('Chapter 2: Classical Music and the Classical Mind');
        $expectedChapter->setTitle('Chapter 2: Classical Music and the Classical Mind');

        $chapter = DAORegistry::getDAO('ChapterDAO')->newDataObject();
        $chapter->setTitle('Chapter 2: Classical Music and the Classical Mind');

        $chapter = $this->thothService->registerChapter($chapter);
        $this->assertEquals($expectedChapter, $chapter);
    }

    public function testRegisterRelation()
    {
        $relatedWorkId = '7d861db5-22f6-4ef8-abbb-b56ab8397624';

        $expectedRelation = new ThothWorkRelation();
        $expectedRelation->setId('3e587b61-58f1-4064-bf80-e40e5c924d27');
        $expectedRelation->setRelatorWorkId('74fde3e2-ca4e-4597-bb0c-aee90648f5a5');
        $expectedRelation->setRelatedWorkId($relatedWorkId);
        $expectedRelation->setRelationType(ThothWorkRelation::RELATION_TYPE_IS_CHILD_OF);
        $expectedRelation->setRelationOrdinal(5);

        $chapter = DAORegistry::getDAO('ChapterDAO')->newDataObject();
        $chapter->setTitle('Epilogue');
        $chapter->setSequence(4);

        $relation = $this->thothService->registerRelation($chapter, $relatedWorkId);
        $this->assertEquals($expectedRelation, $relation);
    }

    public function testRegisterPublication()
    {
        $workId = '2a065323-76cd-4f54-b83b-19f2a925f426';

        $expectedPublication = new ThothPublication();
        $expectedPublication->setId('80359118-9b33-4cf4-a4b4-8784e6d4375a');
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

        $publication = $this->thothService->registerPublication($publicationFormat, $workId);
        $this->assertEquals($expectedPublication, $publication);
    }

    public function testRegisterLocation()
    {
        $publicationId = '8ac3e585-c32a-42d7-bd36-ef42ee397e6e';

        $expectedLocation = new ThothLocation();
        $expectedLocation->setId('03b0367d-bba3-4e26-846a-4c36d3920db2');
        $expectedLocation->setPublicationId($publicationId);
        $expectedLocation->setLandingPage('https://omp.publicknowledgeproject.org/index.php/press/catalog/book/53');
        $expectedLocation->setFullTextUrl('https://www.bookstore.com/site/books/book5');
        $expectedLocation->setLocationPlatform(ThothLocation::LOCATION_PLATFORM_OTHER);
        $expectedLocation->setCanonical(true);

        $publicationFormat = DAORegistry::getDAO('PublicationFormatDAO')->newDataObject();
        $publicationFormat->setId(41);
        $publicationFormat->setRemoteUrl('https://www.bookstore.com/site/books/book5');

        $location = $this->thothService->registerLocation($publicationFormat, $publicationId);
        $this->assertEquals($expectedLocation, $location);
    }
}

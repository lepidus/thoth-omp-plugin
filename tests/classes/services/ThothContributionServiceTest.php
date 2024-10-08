<?php

/**
 * @file plugins/generic/thoth/tests/classes/services/ThothContributionServiceTest.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Copyright (c) 2024 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothContributionServiceTest
 * @ingroup plugins_generic_thoth_tests
 * @see ThothContributionService
 *
 * @brief Test class for the ThothContributionService class
 */

import('lib.pkp.tests.PKPTestCase');
import('classes.monograph.Author');
import('classes.publication.Publication');
import('plugins.generic.thoth.classes.services.ThothContributionService');
import('plugins.generic.thoth.lib.thothAPI.ThothClient');

class ThothContributionServiceTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->contributionService = new ThothContributionService();
        $this->setUpMockEnvironment();
    }

    protected function tearDown(): void
    {
        unset($this->contributionService);
        parent::tearDown();
    }

    protected function getMockedDAOs()
    {
        return ['UserGroupDAO', 'PublicationDAO'];
    }

    private function setUpMockEnvironment()
    {
        $userGroupMockDao = $this->getMockBuilder(UserGroupDAO::class)
            ->setMethods(['getById'])
            ->getMock();

        $userGroup = new UserGroup();
        $userGroup->setData('nameLocaleKey', 'default.groups.name.author');

        $userGroupMockDao->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($userGroup));

        DAORegistry::registerDAO('UserGroupDAO', $userGroupMockDao);

        $publicationMockDao = $this->getMockBuilder(PublicationDAO::class)
            ->setMethods(['getById'])
            ->getMock();

        $publication = new Publication();
        $publication->setData('primaryContactId', 7);

        $publicationMockDao->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($publication));

        DAORegistry::registerDAO('PublicationDAO', $publicationMockDao);
    }

    public function testGettingContributionTypeByUserGroupLocaleKey()
    {
        $this->assertEquals(
            ThothContribution::CONTRIBUTION_TYPE_AUTHOR,
            $this->contributionService->getContributionTypeByUserGroupLocaleKey('default.groups.name.author')
        );
        $this->assertEquals(
            ThothContribution::CONTRIBUTION_TYPE_AUTHOR,
            $this->contributionService->getContributionTypeByUserGroupLocaleKey('default.groups.name.chapterAuthor')
        );
        $this->assertEquals(
            ThothContribution::CONTRIBUTION_TYPE_EDITOR,
            $this->contributionService->getContributionTypeByUserGroupLocaleKey('default.groups.name.volumeEditor')
        );
        $this->assertEquals(
            ThothContribution::CONTRIBUTION_TYPE_TRANSLATOR,
            $this->contributionService->getContributionTypeByUserGroupLocaleKey('default.groups.name.translator')
        );
    }

    public function testCreateNewContributionByAuthor()
    {
        $expectedContribution = new ThothContribution();
        $expectedContribution->setContributionType(ThothContribution::CONTRIBUTION_TYPE_AUTHOR);
        $expectedContribution->setMainContribution(true);
        $expectedContribution->setContributionOrdinal(1);
        $expectedContribution->setFirstName('Reza');
        $expectedContribution->setLastName('Negarestani');
        $expectedContribution->setFullName('Reza Negarestani');
        $expectedContribution->setBiography(
            'Reza Negarestani is a philosopher. His current philosophical project is focused on ' .
            'rationalist universalism beginning with the evolution of the modern system of knowledge and ' .
            'advancing toward contemporary philosophies of rationalism.'
        );

        $author = new Author();
        $author->setId(7);
        $author->setGivenName('Reza', 'en_US');
        $author->setFamilyName('Negarestani', 'en_US');
        $author->setSequence(0);
        $author->setUserGroupId(2);
        $author->setBiography(
            'Reza Negarestani is a philosopher. His current philosophical project is focused on rationalist ' .
            'universalism beginning with the evolution of the modern system of knowledge and ' .
            'advancing toward contemporary philosophies of rationalism.',
            'en_US'
        );

        $contribution = $this->contributionService->newByAuthor($author);
        $this->assertEquals($expectedContribution, $contribution);
    }

    public function testCreateNewContribution()
    {
        $expectedContribution = new ThothContribution();
        $expectedContribution->setContributionType(ThothContribution::CONTRIBUTION_TYPE_EDITOR);
        $expectedContribution->setMainContribution(false);
        $expectedContribution->setContributionOrdinal(3);
        $expectedContribution->setLastName('Steyerl');
        $expectedContribution->setFullName('Hito Steyerl');

        $params = [
            'contributionType' => ThothContribution::CONTRIBUTION_TYPE_EDITOR,
            'mainContribution' => false,
            'contributionOrdinal' => 3,
            'lastName' => 'Steyerl',
            'fullName' => 'Hito Steyerl'
        ];

        $contribution = $this->contributionService->new($params);
        $this->assertEquals($expectedContribution, $contribution);
    }

    public function testRegisterContribution()
    {
        $expectedContribution = new ThothContribution();
        $expectedContribution->setId('67afac83-b015-4f32-9576-60b665a9e685');
        $expectedContribution->setWorkId('45a6622c-a306-4559-bb77-25367dc881b8');
        $expectedContribution->setContributorId('f70f709e-2137-4c87-a2e5-d52b263759ec');
        $expectedContribution->setContributionType(ThothContribution::CONTRIBUTION_TYPE_AUTHOR);
        $expectedContribution->setMainContribution(false);
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

        $mockThothClient = $this->getMockBuilder(ThothClient::class)
            ->setMethods(['createContribution','contributors'])
            ->getMock();
        $mockThothClient->expects($this->any())
            ->method('createContribution')
            ->will($this->returnValue('67afac83-b015-4f32-9576-60b665a9e685'));
        $mockThothClient->expects($this->any())
            ->method('contributors')
            ->will($this->returnValue([
                [
                    'contributorId' => 'f70f709e-2137-4c87-a2e5-d52b263759ec',
                    'lastName' => 'Wilson',
                    'fullName' => 'Michael Wilson'
                ]
            ]));

        $contribution = $this->contributionService->register($mockThothClient, $author, '45a6622c-a306-4559-bb77-25367dc881b8');
        $this->assertEquals($expectedContribution, $contribution);
    }
}

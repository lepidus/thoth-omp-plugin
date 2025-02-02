<?php

/**
 * @file plugins/generic/thoth/tests/classes/services/ThothAffiliationServiceTest.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Copyright (c) 2024 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothAffiliationServiceTest
 *
 * @ingroup plugins_generic_thoth_tests
 *
 * @see ThothAffiliationService
 *
 * @brief Test class for the ThothAffiliationService class
 */

use PKP\tests\PKPTestCase;
use ThothApi\GraphQL\Client;
use ThothApi\GraphQL\Models\Affiliation as ThothAffiliation;

import('plugins.generic.thoth.classes.services.ThothAffiliationService');

class ThothAffiliationServiceTest extends PKPTestCase
{
    private $clientFactoryBackup;
    private $configFactoryBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientFactoryBackup = ThothContainer::getInstance()->backup('client');
        $this->affiliationService = new ThothAffiliationService();
    }

    protected function tearDown(): void
    {
        unset($this->affiliationService);
        ThothContainer::getInstance()->set('client', $this->clientFactoryBackup);
        parent::tearDown();
    }

    public function testCreateNewAffiliation()
    {
        $expectedThothAffiliation = new ThothAffiliation();
        $expectedThothAffiliation->setAffiliationId('42d407e2-fd07-4c45-853d-74ddfc0a02a8');
        $expectedThothAffiliation->setContributionId('8b4a7128-c483-459c-bb5d-89bf553ddf21');
        $expectedThothAffiliation->setInstitutionId('918ab03e-248b-4cc8-8bf6-1f0c1166d98d');
        $expectedThothAffiliation->setAffiliationOrdinal(1);

        $params = [
            'affiliationId' => '42d407e2-fd07-4c45-853d-74ddfc0a02a8',
            'contributionId' => '8b4a7128-c483-459c-bb5d-89bf553ddf21',
            'institutionId' => '918ab03e-248b-4cc8-8bf6-1f0c1166d98d',
            'affiliationOrdinal' => 1,
        ];

        $thothAffiliation = $this->affiliationService->new($params);

        $this->assertEquals($expectedThothAffiliation, $thothAffiliation);
    }

    public function testRegisterInstitution()
    {
        $expectedThothAffiliation = new ThothAffiliation();
        $expectedThothAffiliation->setAffiliationId('0e721ddc-4ea5-453a-8590-e236a5f2db9b');
        $expectedThothAffiliation->setContributionId('5c0a1fcb-2785-407e-8671-95c662bea559');
        $expectedThothAffiliation->setInstitutionId('8ae6f820-b1ef-400c-852a-729c942bf8f2');
        $expectedThothAffiliation->setAffiliationOrdinal(1);

        $mockThothClient = $this->getMockBuilder(Client::class)
            ->setMethods(['createAffiliation','institutions'])
            ->getMock();
        $mockThothClient->expects($this->any())
            ->method('createAffiliation')
            ->will($this->returnValue('0e721ddc-4ea5-453a-8590-e236a5f2db9b'));
        $mockThothClient->expects($this->any())
            ->method('institutions')
            ->will($this->returnValue([
                new ThothAffiliation([
                    'institutionId' => '8ae6f820-b1ef-400c-852a-729c942bf8f2',
                    'institutionName' => 'Aalborg University',
                    'institutionDoi' => 'https://doi.org/10.13039/501100002702',
                    'countryCode' => 'DNK',
                    'ror' => 'https://ror.org/04m5j1k67'
                ])
            ]));

        ThothContainer::getInstance()->set('client', function () use ($mockThothClient) {
            return $mockThothClient;
        });


        $thothAffiliation = $this->affiliationService->register(
            'Aalborg University',
            '5c0a1fcb-2785-407e-8671-95c662bea559'
        );
        $this->assertEquals($expectedThothAffiliation, $thothAffiliation);
    }
}

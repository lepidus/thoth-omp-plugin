<?php

/**
 * @file plugins/generic/thoth/tests/classes/fixtures/MetadataMappingFixtureTest.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Copyright (c) 2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetadataMappingFixtureTest
 *
 * @ingroup plugins_generic_thoth_tests
 *
 * @brief Test the shared metadata mapping contract fixture.
 */

import('lib.pkp.tests.PKPTestCase');

class MetadataMappingFixtureTest extends PKPTestCase
{
    public function testItCoversEveryPlannedMappingDomain()
    {
        $fixture = $this->getFixture();

        $this->assertSame(
            ['book', 'doi', 'authors', 'formats', 'chapters', 'subjects', 'references'],
            array_keys($fixture)
        );

        foreach ($fixture as $contract) {
            $this->assertSame(['source', 'expected'], array_keys($contract));
        }
    }

    public function testItDefinesNormalizedIdentifierContracts()
    {
        $fixture = $this->getFixture();

        $this->assertSame('https://doi.org/10.1234/open-book', $fixture['book']['expected']['doi']);
        $this->assertSame(
            $fixture['doi']['expected']['bare'],
            $fixture['doi']['expected']['url']
        );
        $this->assertSame(
            'https://orcid.org/0000-0002-1825-0097',
            $fixture['authors']['expected'][0]['orcid']
        );
        $this->assertSame('978-3-16-148410-0', $fixture['formats']['expected'][0]['isbn']);
        $this->assertSame(
            'https://doi.org/10.1234/open-book.chapter-1',
            $fixture['chapters']['expected'][0]['doi']
        );
        $this->assertSame(
            'https://doi.org/10.1234/reference-one',
            $fixture['references']['expected'][0]['doi']
        );
    }

    public function testItDefinesForthcomingWorkAndOrderedCollectionContracts()
    {
        $fixture = $this->getFixture();

        $this->assertSame('FORTHCOMING', $fixture['book']['expected']['workStatus']);
        $this->assertSame('FORTHCOMING', $fixture['chapters']['expected'][0]['workStatus']);
        $this->assertSame([1, 2, 3], array_column($fixture['subjects']['expected'], 'subjectOrdinal'));
        $this->assertSame([1, 2], array_column($fixture['references']['expected'], 'referenceOrdinal'));
        $this->assertArrayNotHasKey('doi', $fixture['references']['expected'][1]);
    }

    private function getFixture()
    {
        return require __DIR__ . '/../../fixtures/metadataMapping.php';
    }
}

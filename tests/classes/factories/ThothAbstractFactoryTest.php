<?php

namespace APP\plugins\generic\thoth\tests\classes\factories;

use APP\plugins\generic\thoth\classes\factories\ThothAbstractFactory;
use PKP\tests\PKPTestCase;

class ThothAbstractFactoryTest extends PKPTestCase
{
    public function testCreateFromPublicationWrapsAbstractWithoutParagraph(): void
    {
        $publication = new class () {
            public function getData($key)
            {
                $values = [
                    'locale' => 'en_US',
                    'abstract' => ['en_US' => 'English abstract'],
                ];

                return $values[$key] ?? null;
            }
        };

        $factory = new ThothAbstractFactory();
        $thothAbstracts = $factory->createFromPublication($publication, 'work-id', 'en_US');

        $this->assertSame('<p>English abstract</p>', $thothAbstracts['EN_US']->getContent());
    }

    public function testCreateFromPublicationPreservesAbstractAlreadyWrappedInParagraph(): void
    {
        $publication = new class () {
            public function getData($key)
            {
                $values = [
                    'locale' => 'en_US',
                    'abstract' => ['en_US' => '<p>English abstract</p>'],
                ];

                return $values[$key] ?? null;
            }
        };

        $factory = new ThothAbstractFactory();
        $thothAbstracts = $factory->createFromPublication($publication, 'work-id', 'en_US');

        $this->assertSame('<p>English abstract</p>', $thothAbstracts['EN_US']->getContent());
    }
}

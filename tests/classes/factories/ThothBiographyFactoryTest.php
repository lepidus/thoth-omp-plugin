<?php

namespace APP\plugins\generic\thoth\tests\classes\factories;

use APP\plugins\generic\thoth\classes\factories\ThothBiographyFactory;
use PKP\tests\PKPTestCase;

class ThothBiographyFactoryTest extends PKPTestCase
{
    public function testCreateFromAuthorWrapsBiographyWithoutParagraph(): void
    {
        $author = new class () {
            public function getData($key)
            {
                $values = [
                    'locale' => 'en_US',
                    'biography' => ['en_US' => 'English biography'],
                ];

                return $values[$key] ?? null;
            }
        };

        $factory = new ThothBiographyFactory();
        $thothBiographies = $factory->createFromAuthor($author, 'contribution-id', 'en_US');

        $this->assertSame('<p>English biography</p>', $thothBiographies['EN_US']->getContent());
    }

    public function testCreateFromAuthorPreservesBiographyAlreadyWrappedInParagraph(): void
    {
        $author = new class () {
            public function getData($key)
            {
                $values = [
                    'locale' => 'en_US',
                    'biography' => ['en_US' => '<p>English biography</p>'],
                ];

                return $values[$key] ?? null;
            }
        };

        $factory = new ThothBiographyFactory();
        $thothBiographies = $factory->createFromAuthor($author, 'contribution-id', 'en_US');

        $this->assertSame('<p>English biography</p>', $thothBiographies['EN_US']->getContent());
    }
}

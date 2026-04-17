<?php

namespace APP\plugins\generic\thoth\tests\classes\services;

use APP\plugins\generic\thoth\classes\factories\ThothBiographyFactory;
use APP\plugins\generic\thoth\classes\repositories\ThothBiographyRepository;
use APP\plugins\generic\thoth\classes\services\ThothBiographyService;
use PKP\tests\PKPTestCase;
use ThothApi\GraphQL\Client as ThothClient;

class ThothBiographyServiceTest extends PKPTestCase
{
    public function testUpdateByAuthor()
    {
        $mockRepository = $this->getMockBuilder(ThothBiographyRepository::class)
            ->setConstructorArgs([$this->getMockBuilder(ThothClient::class)->getMock()])
            ->onlyMethods(['add', 'edit', 'delete'])
            ->getMock();
        $mockRepository->expects($this->once())->method('add');
        $mockRepository->expects($this->once())->method('edit');
        $mockRepository->expects($this->once())->method('delete')->with('removed-biography-id');

        $author = new class () {
            public function getData($key)
            {
                $values = [
                    'locale' => 'en_US',
                    'biography' => [
                        'en_US' => '<p>English biography</p>',
                        'pt_BR' => '<p>Biografia em portugues</p>',
                        'zz_ZZ' => '<p>Unsupported biography</p>',
                    ],
                ];

                return $values[$key] ?? null;
            }
        };

        $service = new ThothBiographyService(new ThothBiographyFactory(), $mockRepository);
        $service->updateByAuthor($author, 'contribution-id', [
            ['biographyId' => 'existing-biography-id', 'localeCode' => 'EN_US', 'canonical' => true],
            ['biographyId' => 'removed-biography-id', 'localeCode' => 'ES', 'canonical' => false],
        ], 'en_US');
    }
}

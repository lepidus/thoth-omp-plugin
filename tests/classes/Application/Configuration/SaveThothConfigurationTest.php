<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Configuration;

use APP\plugins\generic\thoth\classes\Application\Configuration\SaveThothConfiguration;
use APP\plugins\generic\thoth\classes\Contracts\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use BadMethodCallException;
use PKP\tests\PKPTestCase;

class SaveThothConfigurationTest extends PKPTestCase
{
    public function testPersistsConfigurationAndInvalidatesContextCache(): void
    {
        $repository = new class () implements ThothConfigurationRepository {
            public array $saved;

            public function get(int $contextId): ThothConfiguration
            {
                throw new BadMethodCallException();
            }

            public function save(int $contextId, ThothConfiguration $configuration): void
            {
                $this->saved = [$contextId, $configuration];
            }
        };
        $invalidatedContexts = [];
        $service = new SaveThothConfiguration(
            $repository,
            function (int $contextId) use (&$invalidatedContexts): void {
                $invalidatedContexts[] = $contextId;
            }
        );
        $configuration = new ThothConfiguration(true, 'https://api.example.test', 'token');

        $service->execute(7, $configuration);

        $this->assertSame([7, $configuration], $repository->saved);
        $this->assertSame([7], $invalidatedContexts);
    }
}

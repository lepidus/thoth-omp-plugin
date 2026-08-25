<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Configuration;

use APP\plugins\generic\thoth\classes\Application\Configuration\Port\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Application\Configuration\SaveThothConfiguration;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use BadMethodCallException;
use PHPUnit\Framework\TestCase;

class SaveThothConfigurationTest extends TestCase
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

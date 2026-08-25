<?php

use PHPUnit\Framework\TestCase;

class SaveThothConfigurationTest extends TestCase
{
    public function testPersistsConfigurationAndInvalidatesContextCache()
    {
        $repository = new class () implements ThothConfigurationRepository {
            public $saved;

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
            function ($contextId) use (&$invalidatedContexts) {
                $invalidatedContexts[] = $contextId;
            }
        );
        $configuration = new ThothConfiguration(true, 'https://api.example.test', 'token');

        $service->execute(7, $configuration);

        $this->assertSame([7, $configuration], $repository->saved);
        $this->assertSame([7], $invalidatedContexts);
    }
}

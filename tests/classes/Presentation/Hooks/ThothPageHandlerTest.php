<?php

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.plugins.GenericPlugin');

final class ThothPageHandlerTest extends PKPTestCase
{
    public function testItRoutesOnlyKnownThothOperationsToInjectedHandlers(): void
    {
        $plugin = $this->createMock(GenericPlugin::class);
        $plugin->method('getEnabled')->willReturn(true);
        $register = $this->withoutConstructor(RegisterHandler::class);
        $catalog = $this->withoutConstructor(ThothCatalogFilesHandler::class);
        $upload = $this->withoutConstructor(UploadThothFileHandler::class);
        $index = $this->withoutConstructor(ThothHandler::class);
        $router = new ThothPageHandler($plugin, $register, $catalog, $upload, $index);

        $handler = null;
        self::assertTrue($router->addHandlers('LoadHandler', ['thoth', 'register', null, &$handler]));
        self::assertSame($register, $handler);

        $handler = null;
        self::assertFalse($router->addHandlers('LoadHandler', ['thoth', 'unknown', null, &$handler]));
        self::assertNull($handler);
    }

    private function withoutConstructor(string $className): object
    {
        return (new ReflectionClass($className))->newInstanceWithoutConstructor();
    }
}

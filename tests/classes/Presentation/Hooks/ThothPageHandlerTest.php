<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Hooks;

use APP\plugins\generic\thoth\classes\Presentation\Handlers\FileUpload\UploadThothFileHandler;
use APP\plugins\generic\thoth\classes\Presentation\Handlers\Modal\RegisterHandler;
use APP\plugins\generic\thoth\classes\Presentation\Handlers\Pages\ThothCatalogFilesHandler;
use APP\plugins\generic\thoth\classes\Presentation\Handlers\Pages\ThothHandler;
use APP\plugins\generic\thoth\classes\Presentation\Hooks\ThothPageHandler;
use PKP\plugins\GenericPlugin;
use PKP\tests\PKPTestCase;
use ReflectionClass;

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

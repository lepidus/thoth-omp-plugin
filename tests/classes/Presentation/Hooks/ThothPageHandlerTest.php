<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Hooks;

use APP\plugins\generic\thoth\tests\classes\Support\BuildsValidPresentationGraph;
use PKP\plugins\GenericPlugin;
use PKP\tests\PKPTestCase;

final class ThothPageHandlerTest extends PKPTestCase
{
    use BuildsValidPresentationGraph;

    public function testItRoutesOnlyKnownThothOperationsToInjectedHandlers(): void
    {
        $plugin = $this->createMock(GenericPlugin::class);
        $plugin->method('getEnabled')->willReturn(true);
        $graph = $this->buildPageHandler($plugin);
        $register = $graph['register'];
        $router = $graph['router'];

        $handler = null;
        self::assertTrue($router->addHandlers('LoadHandler', ['thoth', 'register', null, &$handler]));
        self::assertSame($register, $handler);

        $handler = null;
        self::assertFalse($router->addHandlers('LoadHandler', ['thoth', 'unknown', null, &$handler]));
        self::assertNull($handler);
    }

}

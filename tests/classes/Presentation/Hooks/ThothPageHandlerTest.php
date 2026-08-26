<?php

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.plugins.GenericPlugin');
require_once dirname(__DIR__, 2) . '/Support/BuildsValidPresentationGraph.php';

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

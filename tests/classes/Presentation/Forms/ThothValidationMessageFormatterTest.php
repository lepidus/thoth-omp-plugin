<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Forms;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Presentation\Forms\ThothValidationMessageFormatter;
use PKP\tests\PKPTestCase;

final class ThothValidationMessageFormatterTest extends PKPTestCase
{
    public function testEscapesEveryValidationMessage(): void
    {
        $html = ThothValidationMessageFormatter::formatWarning([
            'Invalid format <img src=x onerror=alert(1)>',
            'Unsafe & invalid',
        ]);

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringContainsString('Unsafe &amp; invalid', $html);
        $this->assertStringStartsWith('<div class="pkpNotification pkpNotification--warning">', $html);
        $this->assertStringEndsWith('</ul></div>', $html);
    }
}

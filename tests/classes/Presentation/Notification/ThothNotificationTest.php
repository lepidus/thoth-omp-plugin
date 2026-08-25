<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Notification;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Presentation\Notification\ThothNotification;
use PHPUnit\Framework\TestCase;

final class ThothNotificationTest extends TestCase
{
    public function testAddsNotificationEndpointAndScriptToTheBackend(): void
    {
        $templateManager = new NotificationTemplateManagerDouble();
        $notification = new ThothNotification();
        $request = new NotificationRequestDouble();

        $notification->addJavaScriptData($request, $templateManager);
        $notification->addJavaScript($request, $templateManager, new NotificationPluginDouble());

        $inline = $templateManager->scripts['notificationData'];
        $asset = $templateManager->scripts['notification'];
        $this->assertStringContainsString('https://example.test/notification/fetchNotification', $inline['script']);
        $this->assertStringContainsString('thothplugin.notification', $inline['script']);
        $this->assertTrue($inline['options']['inline']);
        $this->assertSame('backend', $inline['options']['contexts']);
        $this->assertSame('https://example.test/plugins/generic/thoth/js/Notification.js', $asset['script']);
        $this->assertSame('backend', $asset['options']['contexts']);
    }
}

final class NotificationTemplateManagerDouble
{
    public array $scripts = [];

    public function addJavaScript(string $name, string $script, array $options): void
    {
        $this->scripts[$name] = ['script' => $script, 'options' => $options];
    }
}

final class NotificationRequestDouble
{
    public function url($context = null, ?string $page = null, ?string $operation = null): string
    {
        return 'https://example.test/' . $page . '/' . $operation;
    }

    public function getBaseUrl(): string
    {
        return 'https://example.test';
    }
}

final class NotificationPluginDouble
{
    public function getPluginPath(): string
    {
        return 'plugins/generic/thoth';
    }
}

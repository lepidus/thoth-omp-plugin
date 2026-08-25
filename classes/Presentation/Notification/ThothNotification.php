<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Notification;

use APP\template\TemplateManager;

final class ThothNotification
{
    public function addJavaScriptData(object $request, object $templateManager): void
    {
        $data = ['notificationUrl' => $request->url(null, 'notification', 'fetchNotification')];
        $output = '$.pkp.plugins.generic = $.pkp.plugins.generic || {};';
        $output .= '$.pkp.plugins.generic.thothplugin = $.pkp.plugins.generic.thothplugin || {};';
        $output .= '$.pkp.plugins.generic.thothplugin.notification = '
            . json_encode($data, JSON_UNESCAPED_SLASHES) . ';';

        $templateManager->addJavaScript('notificationData', $output, [
            'inline' => true,
            'contexts' => 'backend',
        ]);
    }

    public function addJavaScript(object $request, object $templateManager, object $plugin): void
    {
        $templateManager->addJavaScript(
            'notification',
            $request->getBaseUrl() . '/' . $plugin->getPluginPath() . '/js/Notification.js',
            ['contexts' => 'backend', 'priority' => TemplateManager::STYLE_SEQUENCE_LAST]
        );
    }
}

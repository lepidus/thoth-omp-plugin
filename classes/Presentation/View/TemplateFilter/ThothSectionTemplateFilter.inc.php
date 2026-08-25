<?php

final class ThothSectionTemplateFilter
{
    public function addJavaScriptData(object $request, object $templateManager, string $template): bool
    {
        if ($template !== 'dashboard/editors.tpl') {
            return false;
        }

        $dispatcher = $request->getDispatcher();
        $contextPath = $request->getContext()->getData('urlPath');
        $data = [
            'registerTitle' => __('plugins.generic.thoth.register'),
            'registerUrl' => $dispatcher->url(
                $request,
                ROUTE_PAGE,
                null,
                'thoth',
                'register',
                null,
                ['submissionId' => '__submissionId__', 'publicationId' => '__publicationId__']
            ),
            'synchronizeUrl' => $dispatcher->url(
                $request,
                ROUTE_API,
                $contextPath,
                'submissions/__submissionId__/publications/__publicationId__/synchronize'
            ),
            'workStatusUrl' => $dispatcher->url(
                $request,
                ROUTE_API,
                $contextPath,
                'submissions/__submissionId__/thothWorkStatus'
            ),
            'unlinkUrl' => $dispatcher->url(
                $request,
                ROUTE_API,
                $contextPath,
                'submissions/__submissionId__/thothWork'
            ),
            'featureVideoUrl' => $dispatcher->url(
                $request,
                ROUTE_API,
                $contextPath,
                'submissions/__submissionId__/featureVideo'
            ),
        ];
        $output = 'pkp.plugins = pkp.plugins || {};';
        $output .= 'pkp.plugins.generic = pkp.plugins.generic || {};';
        $output .= 'pkp.plugins.generic.thoth = pkp.plugins.generic.thoth || {};';
        $output .= 'pkp.plugins.generic.thoth.workflow = '
            . json_encode($data, JSON_UNESCAPED_SLASHES) . ';';
        $templateManager->addJavaScript('workflowData', $output, [
            'inline' => true,
            'contexts' => 'backend',
        ]);

        return false;
    }

    public function addJavaScript(object $request, object $templateManager, object $plugin): void
    {
        $templateManager->addJavaScript(
            'thothPlugin',
            $request->getBaseUrl() . '/' . $plugin->getPluginPath() . '/public/build/build.iife.js',
            [
                'inline' => false,
                'contexts' => ['backend'],
                'priority' => STYLE_SEQUENCE_LAST,
            ]
        );
    }

    public function addStyleSheet(object $request, object $templateManager, object $plugin): void
    {
        $cssFile = $plugin->getPluginPath() . '/public/build/build.css';
        if (!is_file(BASE_SYS_DIR . '/' . $cssFile)) {
            return;
        }

        $templateManager->addStyleSheet(
            'thothPluginStyle',
            $request->getBaseUrl() . '/' . $cssFile,
            ['contexts' => ['backend']]
        );
    }
}

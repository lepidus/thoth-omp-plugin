<?php

final class ThothSectionTemplateFilter
{
    private ?object $plugin = null;

    public function registerFilter(object $templateManager, string $template, object $plugin): bool
    {
        if ($template !== 'workflow/workflow.tpl') {
            return false;
        }

        $this->plugin = $plugin;
        $templateManager->registerFilter('output', [$this, 'addSection']);

        return false;
    }

    public function addSection(string $output, object $templateManager): string
    {
        if ($this->plugin === null) {
            return $output;
        }

        $pattern = '/<span\s+class="pkpPublication__status">([\s\S]*?)<\/span>[^<]+<\/span>/';
        if (!preg_match($pattern, $output, $matches, PREG_OFFSET_CAPTURE)) {
            return $output;
        }

        $match = $matches[0][0];
        $offset = $matches[0][1] + strlen($match);
        $section = $templateManager->fetch(
            $this->plugin->getTemplateResource('workflow/thothSection.tpl')
        );
        $templateManager->unregisterFilter('output', [$this, 'addSection']);

        return substr($output, 0, $offset) . $section . substr($output, $offset);
    }

    public function addJavaScriptData(object $request, object $templateManager, string $template): bool
    {
        if ($template !== 'workflow/workflow.tpl') {
            return false;
        }

        $submission = $templateManager->getTemplateVars('submission');
        if (!$submission) {
            return false;
        }

        $dispatcher = $request->getDispatcher();
        $contextPath = $request->getContext()->getData('urlPath');
        $data = [
            'hasLinkedWork' => (bool) $submission->getData('thothWorkId'),
            'thothWorkId' => (string) $submission->getData('thothWorkId'),
            'submissionStatus' => (int) $submission->getData('status'),
            'registerTitle' => __('plugins.generic.thoth.register'),
            'unlinkTitle' => __('plugins.generic.thoth.unlink'),
            'unlinkConfirm' => __('plugins.generic.thoth.unlink.confirm'),
            'cancelLabel' => __('common.cancel'),
            'connectionError' => __('plugins.generic.thoth.connectionError'),
            'statusError' => __('common.error'),
            'statusNotFound' => __('plugins.generic.thoth.status.notFound'),
            'statusUnregistered' => __('plugins.generic.thoth.status.unregistered'),
            'workStatusLabels' => [
                'ACTIVE' => __('plugins.generic.thoth.workStatus.active'),
                'FORTHCOMING' => __('plugins.generic.thoth.workStatus.forthcoming'),
                'WITHDRAWN' => __('plugins.generic.thoth.workStatus.withdrawn'),
                'SUPERSEDED' => __('plugins.generic.thoth.workStatus.superseded'),
                'POSTPONED_INDEFINITELY' => __('plugins.generic.thoth.workStatus.postponedIndefinitely'),
                'CANCELLED' => __('plugins.generic.thoth.workStatus.cancelled'),
            ],
            'registerUrl' => $dispatcher->url(
                $request,
                ROUTE_PAGE,
                null,
                'thoth',
                'register',
                null,
                ['submissionId' => $submission->getId(), 'publicationId' => '__publicationId__']
            ),
            'synchronizeUrl' => $dispatcher->url(
                $request,
                ROUTE_API,
                $contextPath,
                'submissions/' . $submission->getId() . '/publications/__publicationId__/synchronize'
            ),
            'workStatusUrl' => $dispatcher->url(
                $request,
                ROUTE_API,
                $contextPath,
                'submissions/' . $submission->getId() . '/thothWorkStatus'
            ),
            'unlinkUrl' => $dispatcher->url(
                $request,
                ROUTE_API,
                $contextPath,
                'submissions/' . $submission->getId() . '/thothWork'
            ),
            'featureVideoUrl' => $dispatcher->url(
                $request,
                ROUTE_API,
                $contextPath,
                'submissions/' . $submission->getId() . '/featureVideo'
            ),
        ];
        $output = '$.pkp.plugins.generic = $.pkp.plugins.generic || {};';
        $output .= '$.pkp.plugins.generic.thothplugin = '
            . '$.pkp.plugins.generic.thothplugin || {};';
        $output .= '$.pkp.plugins.generic.thothplugin.workflow = '
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

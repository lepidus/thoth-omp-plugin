<?php

namespace APP\plugins\generic\thoth\classes\Presentation\View;

use APP\controllers\grid\catalogEntry\PublicationFormatGridHandler;
use APP\core\Application;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\Hook;
use PKP\security\Role;

final class PublicationFormatGridModifier
{
    private array $thothFilesActions = [];

    public function __construct(private object $request)
    {
    }

    public function register(): void
    {
        Hook::add('TemplateManager::fetch', [$this, 'addThothActions']);
        Hook::add('TemplateManager::fetch', [$this, 'addThothFilesColumn']);
    }

    public function addThothActions(string $hookName, array $args): bool
    {
        $templateManager = $args[0];
        $template = $args[1];
        if ($template !== 'controllers/grid/gridCell.tpl') {
            return false;
        }

        $actions = $templateManager->getTemplateVars('actions');
        if (!is_array($actions) || count($actions) !== 2) {
            return false;
        }

        $handler = $this->request->getRouter()->getHandler();
        if (!$handler instanceof PublicationFormatGridHandler || !$this->canManagePublicationFormats($handler)) {
            return false;
        }

        $submission = $handler->getSubmission();
        $workId = $submission?->getData('thothWorkId');
        if (!$workId) {
            return false;
        }

        $publicationFormat = $templateManager->getTemplateVars('categoryRow')->getData();
        $actionArgs = [
            'submissionId' => $submission->getId(),
            'representationId' => $publicationFormat->getId(),
            'publicationId' => $publicationFormat->getData('publicationId'),
            'thothWorkId' => $workId,
        ];
        $title = __('plugins.generic.thoth.grid.action.thothFileUpload');
        $actions[] = new LinkAction(
            'thothUpload',
            new AjaxModal(
                $this->request->getDispatcher()->url(
                    $this->request,
                    Application::ROUTE_PAGE,
                    null,
                    'thoth',
                    'uploadThothPublicationFile',
                    null,
                    $actionArgs
                ),
                $title
            ),
            $title
        );
        $templateManager->assign('actions', $actions);

        return false;
    }

    public function addThothFilesColumn(string $hookName, array $args): bool
    {
        $templateManager = $args[0];
        $template = $args[1];
        if ($template !== 'controllers/grid/grid.tpl') {
            return false;
        }

        $handler = $this->request->getRouter()->getHandler();
        if (!$handler instanceof PublicationFormatGridHandler || !$this->canManagePublicationFormats($handler)) {
            return false;
        }

        $submission = $handler->getSubmission();
        if (!$submission || !$submission->getData('thothWorkId')) {
            return false;
        }

        $this->thothFilesActions = [];
        $templateManager->registerFilter('output', [$this, 'injectThothFilesColumn']);

        return false;
    }

    public function injectThothFilesColumn(string $output, object $templateManager): string
    {
        $templateManager->unregisterFilter('output', [$this, 'injectThothFilesColumn']);
        $output = $this->injectThothFilesColumnGroups($output);
        $output = $this->injectThothFilesHeader($output);
        $output = $this->injectThothFilesBodyCells($output);
        $output = $this->injectThothFilesCategoryActions($output, $templateManager);

        return $this->increaseColspans($output);
    }

    private function canManagePublicationFormats(PublicationFormatGridHandler $handler): bool
    {
        $userRoles = $handler->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        return array_intersect(
            $userRoles,
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT]
        ) !== [];
    }

    private function injectThothFilesColumnGroups(string $output): string
    {
        return (string) preg_replace(
            '/(<col class="grid-column column-name"[^>]*\/>)/',
            '$1<col class="grid-column column-thothFiles" style="width: 15%;" />',
            $output
        );
    }

    private function injectThothFilesHeader(string $output): string
    {
        return (string) preg_replace(
            '/(<thead>\s*<tr>\s*<th\b[\s\S]*?<\/th>)/',
            '$1<th scope="col" style="text-align: left;">'
                . htmlspecialchars(__('plugins.generic.thoth.grid.column.thothFiles'), ENT_QUOTES, 'UTF-8')
                . '</th>',
            $output,
            1
        );
    }

    private function injectThothFilesBodyCells(string $output): string
    {
        return (string) preg_replace(
            '/(<tr\b[^>]*class="[^"]*\bgridRow\b[^"]*"[^>]*>[\s\S]*?<\/td>)/',
            '$1<td></td>',
            $output
        );
    }

    private function injectThothFilesCategoryActions(string $output, object $templateManager): string
    {
        return (string) preg_replace_callback(
            '/(<tbody id="[^"]*-category-(\d+)"[^>]*>\s*<tr\b[^>]*class="[^"]*\bcategory\b[^"]*"[^>]*>'
                . '[\s\S]*?<td>)(<\/td>)/',
            function (array $matches) use ($templateManager): string {
                return $matches[1]
                    . $this->getThothFilesActionHtml((int) $matches[2], $templateManager)
                    . $matches[3];
            },
            $output
        );
    }

    private function increaseColspans(string $output): string
    {
        return (string) preg_replace_callback(
            '/colspan="(\d+)"/',
            fn (array $matches): string => 'colspan="' . ((int) $matches[1] + 1) . '"',
            $output
        );
    }

    private function getThothFilesActionHtml(int $representationId, object $templateManager): string
    {
        if (!isset($this->thothFilesActions[$representationId])) {
            $this->thothFilesActions[$representationId] = $this->renderThothFilesAction(
                $representationId,
                $templateManager
            );
        }

        return $this->thothFilesActions[$representationId];
    }

    private function renderThothFilesAction(int $representationId, object $templateManager): string
    {
        $handler = $this->request->getRouter()->getHandler();
        $submission = $handler->getSubmission();
        $publication = $handler->getPublication();
        $cellId = 'cell-thothPublicationFormatFiles-' . $representationId;
        $title = __('plugins.generic.thoth.grid.action.viewThothFiles');
        $action = new LinkAction(
            'viewThothFiles',
            new AjaxModal(
                $this->request->getDispatcher()->url(
                    $this->request,
                    Application::ROUTE_PAGE,
                    null,
                    'thoth',
                    'viewThothPublicationFormatFiles',
                    null,
                    [
                        'submissionId' => $submission->getId(),
                        'publicationId' => $publication->getId(),
                        'representationId' => $representationId,
                    ]
                ),
                $title
            ),
            __('plugins.generic.thoth.grid.action.viewThothFilesLabel'),
            'view',
            $title
        );
        $templateManager->assign('action', $action);
        $templateManager->assign('contextId', $cellId);

        return '<span id="' . htmlspecialchars($cellId, ENT_QUOTES, 'UTF-8') . '" class="pkp_linkActions">'
            . $templateManager->fetch('linkAction/linkAction.tpl')
            . '</span>';
    }
}

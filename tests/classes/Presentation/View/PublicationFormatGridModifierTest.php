<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\View;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\controllers\grid\catalogEntry\PublicationFormatGridHandler;
use APP\core\Application;
use APP\plugins\generic\thoth\classes\Presentation\View\PublicationFormatGridModifier;
use PKP\linkAction\LinkAction;
use PKP\security\Role;
use PKP\tests\PKPTestCase;

final class PublicationFormatGridModifierTest extends PKPTestCase
{
    public function testAddsUploadActionForAnAuthorizedLinkedSubmission(): void
    {
        $handler = new PublicationFormatGridHandlerDouble([Role::ROLE_ID_MANAGER]);
        $templateManager = new PublicationFormatGridTemplateManagerDouble([
            'actions' => [new \stdClass(), new \stdClass()],
            'categoryRow' => new PublicationFormatCategoryRowDouble(),
        ]);
        $modifier = new PublicationFormatGridModifier(new PublicationFormatGridRequestDouble($handler));

        $this->assertFalse($modifier->addThothActions('TemplateManager::fetch', [
            $templateManager,
            'controllers/grid/gridCell.tpl',
        ]));

        $action = $templateManager->variables['actions'][2];
        $this->assertInstanceOf(LinkAction::class, $action);
        $this->assertSame('thothUpload', $action->getId());
        $this->assertStringContainsString('submissionId=41', $action->getActionRequest()->getUrl());
        $this->assertStringContainsString('representationId=7', $action->getActionRequest()->getUrl());
        $this->assertStringContainsString('thothWorkId=work-id', $action->getActionRequest()->getUrl());
    }

    public function testLeavesActionsUnchangedWithoutAnAuthorizedRole(): void
    {
        $handler = new PublicationFormatGridHandlerDouble([Role::ROLE_ID_AUTHOR]);
        $templateManager = new PublicationFormatGridTemplateManagerDouble([
            'actions' => [new \stdClass(), new \stdClass()],
            'categoryRow' => new PublicationFormatCategoryRowDouble(),
        ]);
        $modifier = new PublicationFormatGridModifier(new PublicationFormatGridRequestDouble($handler));

        $modifier->addThothActions('TemplateManager::fetch', [
            $templateManager,
            'controllers/grid/gridCell.tpl',
        ]);

        $this->assertCount(2, $templateManager->variables['actions']);
    }

    public function testInjectsFilesColumnActionsAndColspansIntoThePublicationFormatGrid(): void
    {
        $handler = new PublicationFormatGridHandlerDouble([Role::ROLE_ID_ASSISTANT]);
        $templateManager = new PublicationFormatGridTemplateManagerDouble();
        $modifier = new PublicationFormatGridModifier(new PublicationFormatGridRequestDouble($handler));
        $html = '<table><col class="grid-column column-name" />'
            . '<thead><tr><th scope="col">Name</th></tr></thead>'
            . '<tbody id="publicationFormatGrid-category-7"><tr class="gridRow category"><td>EPUB</td></tr></tbody>'
            . '<tbody><tr class="gridRow"><td>File</td></tr></tbody>'
            . '<tfoot><tr><td colspan="2">Footer</td></tr></tfoot></table>';

        $this->assertFalse($modifier->addThothFilesColumn('TemplateManager::fetch', [
            $templateManager,
            'controllers/grid/grid.tpl',
        ]));
        $result = $templateManager->applyOutputFilter($html);

        $this->assertStringContainsString('column-thothFiles', $result);
        $this->assertStringContainsString(
            htmlspecialchars(__('plugins.generic.thoth.grid.column.thothFiles'), ENT_QUOTES, 'UTF-8'),
            $result
        );
        $this->assertStringContainsString('<td></td>', $result);
        $this->assertStringContainsString('<a>viewThothFiles</a>', $result);
        $this->assertStringContainsString('colspan="3"', $result);
        $this->assertTrue($templateManager->filterUnregistered);
    }
}

final class PublicationFormatGridHandlerDouble extends PublicationFormatGridHandler
{
    private array $roles;
    private object $submission;
    private object $publication;

    public function __construct(array $roles)
    {
        $this->roles = $roles;
        $this->submission = new class () {
            public function getId(): int
            {
                return 41;
            }

            public function getData(string $name)
            {
                return $name === 'thothWorkId' ? 'work-id' : null;
            }
        };
        $this->publication = new class () {
            public function getId(): int
            {
                return 13;
            }
        };
    }

    public function getSubmission()
    {
        return $this->submission;
    }

    public function getPublication()
    {
        return $this->publication;
    }

    public function &getAuthorizedContextObject($assocType)
    {
        return $this->roles;
    }
}

final class PublicationFormatGridRequestDouble
{
    public function __construct(private PublicationFormatGridHandler $handler)
    {
    }

    public function getRouter(): object
    {
        return new class ($this->handler) {
            public function __construct(private PublicationFormatGridHandler $handler)
            {
            }

            public function getHandler(): PublicationFormatGridHandler
            {
                return $this->handler;
            }
        };
    }

    public function getDispatcher(): object
    {
        return new class () {
            public function url(
                $request,
                string $route,
                ?string $context,
                ?string $handler,
                ?string $operation,
                ?array $path,
                ?array $params
            ): string {
                return 'https://example.test/' . ($route === Application::ROUTE_PAGE ? 'page/' : 'api/')
                    . $handler . '/' . $operation . '?' . http_build_query($params ?? []);
            }
        };
    }
}

final class PublicationFormatGridTemplateManagerDouble
{
    public array $variables;
    public bool $filterUnregistered = false;
    private $outputFilter;

    public function __construct(array $variables = [])
    {
        $this->variables = $variables;
    }

    public function getTemplateVars(string $name)
    {
        return $this->variables[$name] ?? null;
    }

    public function assign(string $name, $value): void
    {
        $this->variables[$name] = $value;
    }

    public function registerFilter(string $type, callable $filter): void
    {
        $this->outputFilter = $filter;
    }

    public function unregisterFilter(string $type, callable $filter): void
    {
        $this->filterUnregistered = true;
    }

    public function fetch(string $template): string
    {
        return '<a>' . $this->variables['action']->getId() . '</a>';
    }

    public function applyOutputFilter(string $output): string
    {
        return ($this->outputFilter)($output, $this);
    }
}

final class PublicationFormatCategoryRowDouble
{
    public function getData(): object
    {
        return new class () {
            public function getId(): int
            {
                return 7;
            }

            public function getData(string $name): ?int
            {
                return $name === 'publicationId' ? 13 : null;
            }
        };
    }
}

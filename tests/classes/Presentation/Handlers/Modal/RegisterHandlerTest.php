<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Handlers\Modal;

use APP\plugins\generic\thoth\classes\Application\Registration\Port\PublisherAccessGateway;
use APP\plugins\generic\thoth\classes\Application\Registration\Port\RegistrationMetadataValidator;
use APP\plugins\generic\thoth\classes\Presentation\Handlers\Modal\RegisterHandler;
use PKP\plugins\GenericPlugin;
use PKP\tests\PKPTestCase;

final class RegisterHandlerTest extends PKPTestCase
{
    public function testItBuildsTheRegistrationFormFromExplicitCollaborators(): void
    {
        $validator = $this->createMock(RegistrationMetadataValidator::class);
        $validator->method('validate')->willReturn([]);
        $publisher = $this->createMock(PublisherAccessGateway::class);
        $publisher->method('imprints')->willReturn([]);
        $templateManager = new class () {
            public array $assigned = [];

            public function assign(string $key, array $value): void
            {
                $this->assigned[$key] = $value;
            }

            public function fetchJson(string $template): string
            {
                return $template;
            }
        };
        $plugin = $this->createMock(GenericPlugin::class);
        $plugin->method('getTemplateResource')->willReturn('thoth/register.tpl');
        $capturedAction = null;
        $handler = new RegisterHandler(
            $plugin,
            $templateManager,
            $validator,
            $publisher,
            function (string $action) use (&$capturedAction): object {
                $capturedAction = $action;
                return new class () {
                    public function getConfig(): array
                    {
                        return ['id' => 'register'];
                    }
                };
            }
        );
        $handler->submission = new class () {
            public function getId(): int
            {
                return 17;
            }

            public function getData(string $key)
            {
                return ['contextId' => 3, 'workType' => 2][$key] ?? null;
            }
        };
        $handler->publication = new \stdClass();
        $request = new class () {
            public function getContext(): object
            {
                return new class () {
                    public function getId(): int
                    {
                        return 3;
                    }

                    public function getPath(): string
                    {
                        return 'press';
                    }
                };
            }

            public function getDispatcher(): object
            {
                return new class () {
                    public function url($request, $route, $context, $path): string
                    {
                        return '/press/api/v1/' . $path;
                    }
                };
            }
        };

        $handler->register([], $request);

        self::assertSame('/press/api/v1/_submissions/17/register', $capturedAction);
        self::assertSame(['components' => ['register' => ['id' => 'register']]], $templateManager->assigned['registerData']);
    }
}

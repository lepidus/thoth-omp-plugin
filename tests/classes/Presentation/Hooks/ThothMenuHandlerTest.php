<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Hooks;

use APP\plugins\generic\thoth\classes\Presentation\Hooks\ThothMenuHandler;
use PKP\security\Role;
use PKP\tests\PKPTestCase;

final class ThothMenuHandlerTest extends PKPTestCase
{
    public function testManagerReceivesTheThothMenuBeforeSettings(): void
    {
        $handler = new ThothMenuHandler($this->request([Role::ROLE_ID_MANAGER]));
        $templateManager = new class () {
            public array $state = ['menu' => [
                'dashboard' => ['name' => 'Dashboard'],
                'settings' => ['name' => 'Settings'],
            ]];

            public function getState(string $key): array
            {
                return $this->state[$key];
            }

            public function setState(array $state): void
            {
                $this->state = array_merge($this->state, $state);
            }
        };

        $handler->addMenu('TemplateManager::display', [$templateManager]);

        self::assertSame(['dashboard', 'thoth', 'settings'], array_keys($templateManager->state['menu']));
    }

    private function request(array $roles): object
    {
        return new class ($roles) {
            public function __construct(private array $roles)
            {
            }

            public function getRouter(): object
            {
                return new class ($this->roles) {
                    public function __construct(private array $roles)
                    {
                    }

                    public function getHandler(): object
                    {
                        return new class ($this->roles) {
                            public function __construct(private array $roles)
                            {
                            }

                            public function getAuthorizedContextObject($assocType): array
                            {
                                return $this->roles;
                            }
                        };
                    }

                    public function url(): string
                    {
                        return '/press/thoth';
                    }

                    public function getRequestedPage(): string
                    {
                        return 'dashboard';
                    }
                };
            }
        };
    }
}

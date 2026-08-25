<?php

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');

final class ThothMenuHandlerTest extends PKPTestCase
{
    public function testManagerReceivesTheThothMenuBeforeSettings(): void
    {
        $handler = new ThothMenuHandler($this->request([ROLE_ID_MANAGER]));
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
            private array $roles;
            public function __construct(array $roles)
            {
                $this->roles = $roles;
            }

            public function getRouter(): object
            {
                return new class ($this->roles) {
                    private array $roles;
                    public function __construct(array $roles)
                    {
                        $this->roles = $roles;
                    }

                    public function getHandler(): object
                    {
                        return new class ($this->roles) {
                            private array $roles;
                            public function __construct(array $roles)
                            {
                                $this->roles = $roles;
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

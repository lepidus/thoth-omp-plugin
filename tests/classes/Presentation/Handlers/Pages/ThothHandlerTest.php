<?php

require_once dirname(__DIR__, 5) . '/vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');

final class ThothHandlerTest extends PKPTestCase
{
    public function testSubEditorListIsRestrictedToAssignedSubmissions(): void
    {
        $handler = new class () extends ThothHandler {
            public function __construct()
            {
            }

            public function assignedUser(array $roles, int $userId): ?int
            {
                return $this->assignedUserId($roles, $userId);
            }
        };

        self::assertSame(42, $handler->assignedUser([ROLE_ID_SUB_EDITOR], 42));
    }

    public function testManagerListIsNotRestrictedToAssignments(): void
    {
        $handler = new class () extends ThothHandler {
            public function __construct()
            {
            }

            public function assignedUser(array $roles, int $userId): ?int
            {
                return $this->assignedUserId($roles, $userId);
            }
        };

        self::assertNull($handler->assignedUser([ROLE_ID_MANAGER], 42));
    }
}

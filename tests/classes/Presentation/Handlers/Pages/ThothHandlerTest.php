<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Handlers\Pages;

use APP\plugins\generic\thoth\classes\Presentation\Handlers\Pages\ThothHandler;
use PKP\security\Role;
use PKP\tests\PKPTestCase;

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

        self::assertSame(42, $handler->assignedUser([Role::ROLE_ID_SUB_EDITOR], 42));
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

        self::assertNull($handler->assignedUser([Role::ROLE_ID_MANAGER], 42));
    }
}

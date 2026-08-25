<?php

require_once dirname(__DIR__, 5) . '/vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.core.PKPRequest');
import('lib.pkp.classes.security.authorization.PublicationAccessPolicy');
import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');

final class UploadThothFileHandlerTest extends PKPTestCase
{
    public function testTemporaryUploadIncludesCsrfToken(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 5) . '/templates/form/uploadThothPublicationFileForm.tpl'
        );

        self::assertMatchesRegularExpression(
            '/multipart_params:\s*\{ldelim\}\s*csrfToken:\s*\{csrf type="json"\}\s*\{rdelim\}/',
            $template
        );
    }

    public function testTemporaryUploadRequiresValidCsrfToken(): void
    {
        $handler = new class () extends UploadThothFileHandler {
            public function __construct()
            {
            }

            public function validUpload(object $request): bool
            {
                return $this->isValidUploadRequest($request);
            }
        };
        $request = new class () {
            public function checkCSRF(): bool
            {
                return false;
            }
        };

        self::assertFalse($handler->validUpload($request));
    }

    public function testAuthorizationScopesUploadToSubmissionAndPublication(): void
    {
        $handler = new class () extends UploadThothFileHandler {
            public function __construct()
            {
            }

            public function policies(PKPRequest $request, array &$args, array $roles): array
            {
                return $this->getAuthorizationPolicies($request, $args, $roles);
            }
        };
        $args = [];

        $policies = $handler->policies($this->createMock(PKPRequest::class), $args, []);

        self::assertInstanceOf(SubmissionAccessPolicy::class, $policies[0]);
        self::assertInstanceOf(PublicationAccessPolicy::class, $policies[1]);
    }
}

<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Handlers\FileUpload;

use APP\plugins\generic\thoth\classes\Presentation\Handlers\FileUpload\UploadThothFileHandler;
use PKP\core\PKPRequest;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\tests\PKPTestCase;

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

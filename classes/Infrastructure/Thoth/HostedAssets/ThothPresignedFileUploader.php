<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets;

use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothApiUrlGuard;
use RuntimeException;
use ThothApi\GraphQL\Schemas\FileUploadResponse;

final class ThothPresignedFileUploader
{
    public function __construct(
        private object $httpClient,
        private ThothApiUrlGuard $urlGuard
    ) {
    }

    public function put(FileUploadResponse $response, string $filePath): void
    {
        $uploadUrl = $response->getUploadUrl();
        if (!is_string($uploadUrl) || !$this->urlGuard->isSafe($uploadUrl)) {
            throw new RuntimeException('Unsafe Thoth upload URL');
        }
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException('The upload file is unavailable or unreadable');
        }

        $headers = [];
        foreach ($response->getUploadHeaders() ?? [] as $header) {
            $headers[$header->getName()] = $header->getValue();
        }

        $resource = fopen($filePath, 'rb');
        if ($resource === false) {
            throw new RuntimeException('The upload file could not be opened');
        }

        try {
            $this->httpClient->request('PUT', $uploadUrl, [
                'headers' => $headers,
                'body' => $resource,
                'allow_redirects' => false,
            ]);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }
}

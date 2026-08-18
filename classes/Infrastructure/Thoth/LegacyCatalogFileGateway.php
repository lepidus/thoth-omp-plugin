<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Contracts\CatalogFileGateway;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

final class LegacyCatalogFileGateway implements CatalogFileGateway
{
    private object $publicationRepository;

    public function __construct(object $publicationRepository)
    {
        $this->publicationRepository = $publicationRepository;
    }

    public function getByWorkId(WorkId $workId): array
    {
        return array_values(array_filter(array_map(
            [$this, 'formatFile'],
            $this->publicationRepository->getFilesByWorkId($workId->toString())
        )));
    }

    public function formatFile($file): ?array
    {
        $publicationType = null;
        if (is_array($file)) {
            $publicationType = $file['publicationType'] ?? null;
            $file = $file['file'] ?? null;
        }

        if (!$file || !$this->isSafeCdnUrl($file->getCdnUrl())) {
            return null;
        }

        return [
            'url' => $file->getCdnUrl(),
            'label' => $file->getObjectKey() ?: __('common.download'),
            'mimeType' => $file->getMimeType(),
            'publicationType' => $publicationType,
        ];
    }

    private function isSafeCdnUrl($url): bool
    {
        if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        return $parts !== false
            && strtolower($parts['scheme'] ?? '') === 'https'
            && !empty($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }
}

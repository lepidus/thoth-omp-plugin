<?php

final class ThothCatalogFileGateway implements CatalogFileGateway
{
    private const WORK_SELECTION = [
        'publications' => [
            'publicationType',
            'file' => ['cdnUrl', 'objectKey', 'mimeType'],
        ],
    ];

    private ThothRemoteGateway $remote;
    private string $downloadLabel;

    public function __construct(ThothRemoteGateway $remote, string $downloadLabel)
    {
        $this->remote = $remote;
        $this->downloadLabel = $downloadLabel;
    }

    public function getByWorkId(WorkId $workId): array
    {
        $work = $this->remote->call('catalog', 'work', [$workId->toString(), self::WORK_SELECTION]);
        $files = [];

        foreach ($work->getPublications() ?? [] as $publication) {
            $file = $publication->getFile();
            if ($file === null || !$this->isSafeDownloadUrl($file->getCdnUrl())) {
                continue;
            }

            $files[] = [
                'url' => $file->getCdnUrl(),
                'label' => $file->getObjectKey() ?: $this->downloadLabel,
                'mimeType' => $file->getMimeType(),
                'publicationType' => $publication->getPublicationType(),
            ];
        }

        return $files;
    }

    private function isSafeDownloadUrl($url): bool
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

<?php

import('lib.pkp.classes.submission.PKPSubmission');

final class PkpCatalogPublicationFilesProvider implements CatalogPublicationFilesProvider
{
    private const CACHE_PREFIX = 'publication-';
    private const CACHE_TTL = 3600;

    private object $submissionRepository;
    private object $publicationRepository;
    private object $submissionFileRepository;
    private object $chapterDao;
    private object $publicationFormatDao;
    private object $publicationMetadataReader;
    private GetCatalogFiles $catalogFiles;
    private ThothRemoteGateway $remote;
    private object $cacheManager;
    public function __construct(
        object $submissionRepository,
        object $publicationRepository,
        object $submissionFileRepository,
        object $chapterDao,
        object $publicationFormatDao,
        object $publicationMetadataReader,
        GetCatalogFiles $catalogFiles,
        ThothRemoteGateway $remote,
        object $cache
    ) {
        $this->submissionRepository = $submissionRepository;
        $this->publicationRepository = $publicationRepository;
        $this->submissionFileRepository = $submissionFileRepository;
        $this->chapterDao = $chapterDao;
        $this->publicationFormatDao = $publicationFormatDao;
        $this->publicationMetadataReader = $publicationMetadataReader;
        $this->catalogFiles = $catalogFiles;
        $this->remote = $remote;
        $this->cacheManager = $cache;
    }

    public function publicFiles(int $contextId, int $submissionId, int $publicationId): ?array
    {
        $submission = $this->submissionRepository->get($submissionId);
        $publication = $this->publicationRepository->get($publicationId);
        if (!$this->isPublic($contextId, $submission, $publication)) {
            return null;
        }

        $cache = $this->cache($publicationId);
        $cached = $cache->get('catalogFiles');
        $cacheTime = (int) $cache->get('cacheTime');
        if (is_array($cached) && $cacheTime > time() - self::CACHE_TTL) {
            return $cached;
        }
        $assembled = $this->assemble($submission, $publication);
        $cache->set('catalogFiles', $assembled);
        $cache->set('cacheTime', time());

        return $assembled;
    }

    public function formatFiles(int $contextId, int $publicationId, int $representationId): ?array
    {
        $publication = $this->publicationRepository->get($publicationId);
        if ($publication === null) {
            return null;
        }
        $submission = $this->submissionRepository->get((int) $publication->getData('submissionId'));
        $format = $this->publicationFormatDao->getById($representationId, $publicationId);
        if ($submission === null || $format === null || (int) $submission->getData('contextId') !== $contextId) {
            return null;
        }
        $type = $this->publicationMetadataReader->publicationTypeForFormat(
            $format,
            $this->submissionFileForFormat($submission, $representationId)
        );
        $catalog = $this->publicFiles($contextId, (int) $submission->getId(), $publicationId);
        if ($catalog === null) {
            return null;
        }

        $files = [];
        $monograph = $this->firstByType($catalog['monograph'], $type);
        if ($monograph !== null) {
            $files[] = [
                'component' => __(
                    'plugins.generic.thoth.publicationFormat.thothFiles.component.publication',
                    ['title' => $publication->getLocalizedTitle()]
                ),
                'file' => $monograph,
            ];
        }
        foreach ($this->chapters($publicationId) as $chapter) {
            $chapterFile = $this->firstByType($catalog['chapters'][(int) $chapter->getId()] ?? [], $type);
            if ($chapterFile !== null) {
                $files[] = [
                    'component' => __(
                        'plugins.generic.thoth.publicationFormat.thothFiles.component.chapter',
                        ['title' => $chapter->getLocalizedTitle()]
                    ),
                    'file' => $chapterFile,
                ];
            }
        }

        return $files;
    }

    public function clientCache(int $publicationId): array
    {
        $cacheTime = $this->cache($publicationId)->get('cacheTime');

        return [
            'ttl' => self::CACHE_TTL,
            'keySuffix' => (string) ($cacheTime ?: time()),
        ];
    }

    private function assemble(object $submission, object $publication): array
    {
        $assembled = ['monograph' => [], 'chapters' => []];
        try {
            $workId = trim((string) $submission->getData('thothWorkId'));
            $assembled['monograph'] = $this->addRepresentationIds(
                $this->catalogFiles->execute($workId === '' ? null : new WorkId($workId)),
                $publication
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
        }

        foreach ($this->chapters((int) $publication->getId()) as $chapter) {
            $doi = trim((string) $chapter->getStoredPubId('doi'));
            if ($doi === '') {
                continue;
            }
            try {
                $remoteChapter = $this->remote->call('catalog', 'chapterByDoi', [
                    $this->doiUrl($doi),
                    ['workId'],
                ]);
                $remoteWorkId = trim((string) $remoteChapter->getWorkId());
                if ($remoteWorkId !== '') {
                    $files = $this->catalogFiles->execute(new WorkId($remoteWorkId));
                    if ($files !== []) {
                        $assembled['chapters'][(int) $chapter->getId()] = $files;
                    }
                }
            } catch (Throwable $exception) {
                error_log($exception->getMessage());
            }
        }

        return $assembled;
    }

    private function addRepresentationIds(array $files, object $publication): array
    {
        $byType = [];
        foreach ($this->publicationFormatDao->getByPublicationId($publication->getId()) as $format) {
            $type = $this->publicationMetadataReader->publicationTypeForFormat(
                $format,
                $this->submissionFileForFormat(
                    $this->submissionRepository->get((int) $publication->getData('submissionId')),
                    (int) $format->getId()
                )
            );
            $byType[$type] ??= (int) $format->getId();
        }
        foreach ($files as &$file) {
            $type = $file['publicationType'] ?? null;
            if (is_string($type) && isset($byType[$type])) {
                $file['representationId'] = $byType[$type];
            }
        }

        return $files;
    }

    private function submissionFileForFormat(?object $submission, int $formatId): ?object
    {
        if ($submission === null) {
            return null;
        }
        $files = $this->submissionFileRepository->getMany([
            'submissionIds' => [(int) $submission->getId()],
            'assocTypes' => [ASSOC_TYPE_PUBLICATION_FORMAT],
            'assocIds' => [$formatId],
        ]);
        foreach ($files as $file) {
            if ((int) $file->getData('assocId') === $formatId && $file->getData('chapterId') === null) {
                return $file;
            }
        }

        return null;
    }

    private function isPublic(int $contextId, ?object $submission, ?object $publication): bool
    {
        return $submission !== null
            && $publication !== null
            && (int) $publication->getData('submissionId') === (int) $submission->getId()
            && (int) $submission->getData('contextId') === $contextId
            && (int) $publication->getData('status') === STATUS_PUBLISHED;
    }

    private function chapters(int $publicationId): array
    {
        $result = $this->chapterDao->getByPublicationId($publicationId);
        if (is_object($result) && method_exists($result, 'toAssociativeArray')) {
            return array_values($result->toAssociativeArray());
        }

        return is_array($result) ? array_values($result) : array_values(iterator_to_array($result));
    }

    private function firstByType(array $files, string $type): ?array
    {
        foreach ($files as $file) {
            if (($file['publicationType'] ?? null) === $type) {
                return $file;
            }
        }

        return null;
    }

    private function doiUrl(string $doi): string
    {
        return strpos($doi, 'https://doi.org/') === 0 ? $doi : 'https://doi.org/' . $doi;
    }

    private function cache(int $publicationId): object
    {
        return $this->cacheManager->getFileCache(
            'thothCatalogFiles',
            self::CACHE_PREFIX . $publicationId,
            static function () {
                return null;
            }
        );
    }
}

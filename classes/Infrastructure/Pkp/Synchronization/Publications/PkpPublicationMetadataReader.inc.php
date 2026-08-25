<?php

use Biblys\Isbn\Isbn;
use ThothApi\GraphQL\Enums\LocationPlatform;
use ThothApi\GraphQL\Enums\PublicationType;

final class PkpPublicationMetadataReader
{
    private const ACCESSIBILITY_FIELDS = [
        'accessibilityStandard',
        'accessibilityAdditionalStandard',
        'accessibilityException',
        'accessibilityReportUrl',
    ];
    private const PHYSICAL_TYPES = [
        'BC' => PublicationType::PAPERBACK,
        'BB' => PublicationType::HARDBACK,
    ];
    private const DIGITAL_TYPES = [
        'html' => PublicationType::HTML,
        'htm' => PublicationType::HTML,
        'xhtml' => PublicationType::HTML,
        'pdf' => PublicationType::PDF,
        'xml' => PublicationType::XML,
        'jats' => PublicationType::XML,
        'epub' => PublicationType::EPUB,
        'mobi' => PublicationType::MOBI,
        'azw3' => PublicationType::AZW3,
        'doc' => PublicationType::DOCX,
        'docx' => PublicationType::DOCX,
        'fb2' => PublicationType::FICTION_BOOK,
        'fictionbook' => PublicationType::FICTION_BOOK,
        'wav' => PublicationType::WAV,
        'mp3' => PublicationType::MP3,
        'm4a' => PublicationType::MP3,
        'aac' => PublicationType::MP3,
        'flac' => PublicationType::MP3,
        'ogg' => PublicationType::MP3,
        'oga' => PublicationType::MP3,
        'audio' => PublicationType::MP3,
    ];
    private const MIME_TYPES = [
        'text/html' => PublicationType::HTML,
        'application/xhtml+xml' => PublicationType::HTML,
        'application/pdf' => PublicationType::PDF,
        'application/xml' => PublicationType::XML,
        'text/xml' => PublicationType::XML,
        'application/jats+xml' => PublicationType::XML,
        'application/epub+zip' => PublicationType::EPUB,
        'application/x-mobipocket-ebook' => PublicationType::MOBI,
        'application/vnd.amazon.ebook' => PublicationType::AZW3,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => PublicationType::DOCX,
        'application/msword' => PublicationType::DOCX,
        'application/x-fictionbook+xml' => PublicationType::FICTION_BOOK,
        'audio/wav' => PublicationType::WAV,
        'audio/wave' => PublicationType::WAV,
        'audio/x-wav' => PublicationType::WAV,
        'audio/mpeg' => PublicationType::MP3,
        'audio/mp3' => PublicationType::MP3,
        'audio/mp4' => PublicationType::MP3,
        'audio/x-m4a' => PublicationType::MP3,
        'audio/aac' => PublicationType::MP3,
        'audio/flac' => PublicationType::MP3,
        'audio/ogg' => PublicationType::MP3,
    ];

    private object $publicationFormatDao;
    private object $submissionFileService;
    private object $publicationDao;
    private object $submissionDao;
    private object $contextDao;
    private object $request;

    public function __construct(
        object $publicationFormatDao,
        object $submissionFileService,
        object $publicationDao,
        object $submissionDao,
        object $contextDao,
        object $request
    ) {
        $this->publicationFormatDao = $publicationFormatDao;
        $this->submissionFileService = $submissionFileService;
        $this->publicationDao = $publicationDao;
        $this->submissionDao = $submissionDao;
        $this->contextDao = $contextDao;
        $this->request = $request;
    }

    public function fromPublication(object $publication): array
    {
        $files = array_values(array_filter(
            $this->submissionFiles($publication->getData('submissionId')),
            fn (object $file): bool => $file->getData('chapterId') === null
        ));
        $formats = $this->publicationFormatDao->getByPublicationId($publication->getId());
        if (is_object($formats) && method_exists($formats, 'toArray')) {
            $formats = $formats->toArray();
        }

        return $this->map($formats, $this->filesByFormat($files), false);
    }

    public function fromChapter(object $chapter): array
    {
        $publication = $this->required(
            $this->publicationDao->getById($chapter->getData('publicationId')),
            'Publication required to map chapter publications'
        );
        $files = array_values(array_filter(
            $this->submissionFiles($publication->getData('submissionId')),
            fn (object $file): bool => $file->getData('chapterId') === $chapter->getId()
        ));
        $formats = [];
        foreach (array_unique(array_map(fn (object $file): int => $file->getData('assocId'), $files)) as $id) {
            $format = $this->publicationFormatDao->getById($id);
            if ($format !== null) {
                $formats[$id] = $format;
            }
        }

        return $this->map($formats, $this->filesByFormat($files), true);
    }

    private function map(iterable $formats, array $filesByFormat, bool $chapter): array
    {
        $metadata = [];
        foreach ($formats as $format) {
            $files = $filesByFormat[$format->getId()] ?? [];
            $file = $files[0] ?? null;
            if (!$this->canRegister($format, $file)) {
                continue;
            }

            $publication = [
                'publicationType' => $this->publicationType($format, $file),
                'isbn' => $chapter ? null : $this->isbn($format),
            ];
            foreach (self::ACCESSIBILITY_FIELDS as $field) {
                $value = $format->getData($field);
                $publication[$field] = $value === '' ? null : $value;
            }
            $publication['locations'] = $this->locations($format, $files);
            $metadata[] = $publication;
        }

        return $metadata;
    }

    private function canRegister(object $format, ?object $file): bool
    {
        return $format->getPhysicalFormat()
            || $file !== null
            || !empty($format->getRemoteURL());
    }

    private function submissionFiles(int $submissionId): array
    {
        return iterator_to_array($this->submissionFileService->getMany([
            'submissionIds' => [$submissionId],
            'assocTypes' => [ASSOC_TYPE_PUBLICATION_FORMAT],
        ]));
    }

    private function filesByFormat(array $files): array
    {
        $filesByFormat = [];
        foreach ($files as $file) {
            $filesByFormat[$file->getData('assocId')][] = $file;
        }

        return $filesByFormat;
    }

    private function publicationType(object $format, ?object $file): string
    {
        $entryKey = (string) $format->getEntryKey();
        if ($entryKey !== 'DA') {
            return self::PHYSICAL_TYPES[$entryKey] ?? PublicationType::PDF;
        }

        $candidates = [];
        if ($file !== null) {
            foreach (['getOriginalFileName', 'getServerFileName'] as $method) {
                $fileName = method_exists($file, $method) ? $file->$method() : null;
                $extension = pathinfo((string) $fileName, PATHINFO_EXTENSION);
                if ($extension !== '') {
                    $candidates[] = $extension;
                }
            }
            foreach (['getFileType', 'mimetype', 'mimeType'] as $source) {
                $value = method_exists($file, $source) ? $file->$source() : $file->getData($source);
                if (!empty($value)) {
                    $candidates[] = $value;
                }
            }
        }

        $remoteUrl = $format->getRemoteURL();
        if (!empty($remoteUrl)) {
            $path = parse_url($remoteUrl, PHP_URL_PATH);
            if (is_string($path)) {
                $candidates[] = pathinfo($path, PATHINFO_EXTENSION);
            }
        }
        $names = $format->getData('name');
        foreach (is_array($names) ? $names : [$names] as $name) {
            $candidates[] = $name;
        }

        foreach ($candidates as $candidate) {
            $type = $this->digitalType($candidate);
            if ($type !== null) {
                return $type;
            }
        }

        return PublicationType::PDF;
    }

    private function digitalType($candidate): ?string
    {
        if (!is_scalar($candidate)) {
            return null;
        }
        $candidate = (string) $candidate;
        $label = preg_replace(
            "/[^a-z0-9\/\+\.\-]+/",
            '',
            str_replace([' ', '_', ':'], '', strtolower($candidate))
        );

        return self::DIGITAL_TYPES[$label] ?? self::MIME_TYPES[strtolower(trim($candidate))] ?? null;
    }

    private function isbn(object $format): ?string
    {
        foreach ($format->getIdentificationCodes()->toArray() as $identificationCode) {
            if (!in_array((string) $identificationCode->getCode(), ['15', '24'], true)) {
                continue;
            }

            $isbn = (string) $identificationCode->getValue();
            try {
                $isbn13 = Isbn::convertToIsbn13($isbn);
                return str_replace('-', '', $isbn13) === $isbn ? $isbn13 : $isbn;
            } catch (\Throwable $exception) {
                return $isbn;
            }
        }

        return null;
    }

    private function locations(object $format, array $files): array
    {
        if (empty($files)) {
            return $format->getRemoteURL() ? [$this->location($format)] : [];
        }

        return array_map(fn (object $file): array => $this->location($format, $file->getId()), $files);
    }

    private function location(object $format, ?int $fileId = null): array
    {
        $publication = $this->required(
            $this->publicationDao->getById($format->getData('publicationId')),
            'Publication required to map location metadata'
        );
        $submission = $this->required(
            $this->submissionDao->getById($publication->getData('submissionId')),
            'Submission required to map location metadata'
        );
        $context = $this->required(
            $this->contextDao->getById($submission->getData('contextId')),
            'Context required to map location metadata'
        );
        $dispatcher = $this->request->getDispatcher();
        $metadata = [
            'landingPage' => $dispatcher->url(
                $this->request,
                ROUTE_PAGE,
                $context->getPath(),
                'catalog',
                'book',
                [$submission->getBestId()]
            ),
            'locationPlatform' => LocationPlatform::OTHER,
        ];
        $fullTextUrl = $fileId === null
            ? $format->getRemoteURL()
            : $dispatcher->url(
                $this->request,
                ROUTE_PAGE,
                $context->getPath(),
                'catalog',
                'view',
                [$submission->getBestId(), $format->getBestId(), $fileId]
            );
        if ($fullTextUrl !== null && $fullTextUrl !== '') {
            $metadata['fullTextUrl'] = $fullTextUrl;
        }

        return $metadata;
    }

    private function required(?object $object, string $message): object
    {
        if ($object === null) {
            throw new \RuntimeException($message);
        }

        return $object;
    }
}

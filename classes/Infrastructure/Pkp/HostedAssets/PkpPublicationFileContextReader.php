<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets;

use Biblys\Isbn\Isbn;
use RuntimeException;
use ThothApi\GraphQL\Enums\PublicationType;

final class PkpPublicationFileContextReader
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

    public function __construct(
        private object $chapterDao,
        private object $publicationFormatDao
    ) {
    }

    /**
     * @return array{chapterDoi: ?string, publication: array<string, mixed>}
     */
    public function read(int $publicationId, int $representationId, int $submissionComponentId): array
    {
        $chapterDoi = null;
        if ($submissionComponentId !== 0 && $submissionComponentId !== $publicationId) {
            $chapter = $this->chapterDao->getChapter($submissionComponentId, $publicationId);
            if ($chapter === null) {
                throw new RuntimeException('Invalid submission component');
            }
            $chapterDoi = $this->doiUrl($chapter->getStoredPubId('doi'));
            if ($chapterDoi === null) {
                throw new RuntimeException('The chapter does not have a DOI');
            }
        }

        $format = $this->publicationFormatDao->getById($representationId, $publicationId);
        if ($format === null) {
            throw new RuntimeException('Invalid publication format');
        }

        $publication = [
            'publicationType' => $this->publicationType($format),
            'isbn' => $this->isbn($format),
        ];
        foreach (self::ACCESSIBILITY_FIELDS as $field) {
            $value = $format->getData($field);
            $publication[$field] = $value === '' ? null : $value;
        }

        return ['chapterDoi' => $chapterDoi, 'publication' => $publication];
    }

    private function publicationType(object $format): string
    {
        $entryKey = (string) $format->getEntryKey();
        if ($entryKey !== 'DA') {
            return self::PHYSICAL_TYPES[$entryKey] ?? PublicationType::PDF;
        }

        $remoteUrl = $format->getData('urlRemote');
        if (is_string($remoteUrl) && $remoteUrl !== '') {
            $path = parse_url($remoteUrl, PHP_URL_PATH);
            $remoteType = is_string($path) ? $this->digitalType(pathinfo($path, PATHINFO_EXTENSION)) : null;
            if ($remoteType !== null) {
                return $remoteType;
            }
        }

        $names = $format->getData('name');
        if (!is_array($names)) {
            $names = [$names];
        }
        foreach ($names as $name) {
            $type = $this->digitalType($name);
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

        $label = preg_replace(
            "/[^a-z0-9\/\+\.\-]+/",
            '',
            str_replace([' ', '_', ':'], '', strtolower((string) $candidate))
        );

        return self::DIGITAL_TYPES[$label] ?? null;
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
            } catch (\Throwable) {
                return $isbn;
            }
        }

        return null;
    }

    private function doiUrl($doi): ?string
    {
        if (!is_string($doi) || trim($doi) === '') {
            return null;
        }

        return 'https://doi.org/' . str_replace(
            ['%', '"', '#', ' ', '<', '>', '{'],
            ['%25', '%22', '%23', '%20', '%3c', '%3e', '%7b'],
            trim($doi)
        );
    }
}

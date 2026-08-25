<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Subjects;

use APP\codelist\SubjectDAO;
use PKP\db\XMLDAO;
use PKP\i18n\interfaces\LocaleInterface;
use ThothApi\GraphQL\Enums\SubjectType;
use Throwable;

final class PkpSubjectClassifier
{
    private const ONIX_SUBJECT_SCHEMES = [
        '03' => SubjectType::LCC,
        '10' => SubjectType::BISAC,
        '12' => SubjectType::BIC,
        '93' => SubjectType::THEMA,
    ];

    private $bicValidator;
    private $themaValidator;
    private string $themaCodesFile;
    private ?array $bicCodes = null;
    private ?array $themaCodes = null;

    public function __construct($bicValidator = null, $themaValidator = null, ?string $themaCodesFile = null)
    {
        $this->bicValidator = $bicValidator;
        $this->themaValidator = $themaValidator;
        $this->themaCodesFile = $themaCodesFile
            ?? __DIR__ . '/../../../../../resources/thema-v1.6-codes.json';
    }

    public function classify($subject): array
    {
        if (
            !is_array($subject)
            && preg_match('/^\s*(03|10|12|93|LCC|BISAC|BIC|THEMA)\s*:\s*(.+?)\s*$/i', (string) $subject, $matches)
        ) {
            $subject = [
                'name' => trim((string) $subject),
                'source' => $matches[1],
                'identifier' => $matches[2],
            ];
        }

        $name = trim((string) ($subject['name'] ?? $subject));
        $identifier = strtoupper(trim((string) ($subject['identifier'] ?? '')));
        $source = trim((string) ($subject['source'] ?? ''));

        if ($identifier !== '' && $source !== '') {
            $subjectType = $this->subjectTypeFromSource($source);
            if ($subjectType !== null && $this->isValidCode($identifier, $subjectType)) {
                return ['subjectType' => $subjectType, 'subjectCode' => $identifier];
            }
            if ($subjectType === null && !$this->isOnixSubjectScheme($source)) {
                return ['subjectType' => SubjectType::CUSTOM, 'subjectCode' => $subject['identifier']];
            }

            return $this->asKeyword($name !== '' ? $name : $identifier);
        }

        $code = strtoupper($name);
        if ($this->isValidCode($code, SubjectType::BISAC)) {
            return ['subjectType' => SubjectType::BISAC, 'subjectCode' => $code];
        }

        $themaMatch = $this->isThemaCode($code);
        if ($themaMatch === true) {
            return ['subjectType' => SubjectType::THEMA, 'subjectCode' => $code];
        }

        $bicMatch = $this->isBicCode($code);
        if ($bicMatch === null || $themaMatch === null) {
            return $this->asKeyword($name);
        }
        if ($bicMatch) {
            return ['subjectType' => SubjectType::BIC, 'subjectCode' => $code];
        }

        return $this->asKeyword($name);
    }

    private function subjectTypeFromSource(string $source): ?string
    {
        $normalizedSource = strtolower(trim($source));
        $aliases = [
            'lcc' => SubjectType::LCC,
            'lc classification' => SubjectType::LCC,
            'bisac' => SubjectType::BISAC,
            'bisacsh' => SubjectType::BISAC,
            'bic' => SubjectType::BIC,
            'bicssc' => SubjectType::BIC,
            'thema' => SubjectType::THEMA,
        ];
        if (isset(self::ONIX_SUBJECT_SCHEMES[$source])) {
            return self::ONIX_SUBJECT_SCHEMES[$source];
        }
        if (isset($aliases[$normalizedSource])) {
            return $aliases[$normalizedSource];
        }

        $url = parse_url($normalizedSource);
        $host = $url['host'] ?? '';
        $path = $url['path'] ?? '';
        if ($host === 'ns.editeur.org' && str_starts_with($path, '/thema')) {
            return SubjectType::THEMA;
        }
        if (in_array($host, ['bic.org.uk', 'www.bic.org.uk'], true) && str_contains($path, 'subject')) {
            return SubjectType::BIC;
        }
        if (in_array($host, ['bisg.org', 'www.bisg.org'], true) && str_contains($path, 'bisac')) {
            return SubjectType::BISAC;
        }
        if (in_array($host, ['id.loc.gov', 'www.loc.gov'], true) && str_contains($path, 'class')) {
            return SubjectType::LCC;
        }

        return null;
    }

    private function isValidCode(string $code, string $subjectType): bool
    {
        return match ($subjectType) {
            SubjectType::BIC => $this->isBicCode($code) === true,
            SubjectType::THEMA => $this->isThemaCode($code) === true,
            SubjectType::BISAC => (bool) preg_match('/^[A-Z]{3}[0-9]{6}$/', $code),
            SubjectType::LCC => (bool) preg_match('/^[A-Z]{1,3}[0-9]+(?:\.[0-9]+)?(?:\.[A-Z][0-9]+)?$/', $code),
            default => false,
        };
    }

    private function isOnixSubjectScheme(string $source): bool
    {
        return (bool) preg_match('/^[0-9A-Z]{2}$/i', trim($source));
    }

    private function isBicCode(string $code): ?bool
    {
        if ($this->bicValidator !== null) {
            return (bool) call_user_func($this->bicValidator, $code);
        }

        if ($this->bicCodes === null) {
            $this->bicCodes = [];
            try {
                $subjectDao = new SubjectDAO();
                $nodeName = $subjectDao->getName();
                $data = (new XMLDAO())->parseStruct(
                    $subjectDao->getFilename(LocaleInterface::DEFAULT_LOCALE),
                    [$nodeName]
                );
                foreach ($data[$nodeName] ?? [] as $subject) {
                    $this->bicCodes[$subject['attributes']['code']] = true;
                }
            } catch (Throwable) {
                return null;
            }
        }

        return isset($this->bicCodes[$code]);
    }

    private function isThemaCode(string $code): ?bool
    {
        if ($this->themaValidator !== null) {
            return (bool) call_user_func($this->themaValidator, $code);
        }
        if (!preg_match('/^[A-Y][A-Z0-9]{0,5}$/', $code)) {
            return false;
        }

        if ($this->themaCodes === null && !$this->loadThemaCodes()) {
            return null;
        }

        return isset($this->themaCodes[$code]);
    }

    private function loadThemaCodes(): bool
    {
        if (!is_readable($this->themaCodesFile)) {
            return false;
        }

        $contents = file_get_contents($this->themaCodesFile);
        $data = $contents === false ? null : json_decode($contents, true);
        if (!is_array($data['codes'] ?? null)) {
            return false;
        }

        $this->themaCodes = array_fill_keys($data['codes'], true);
        return true;
    }

    private function asKeyword(string $subject): array
    {
        return ['subjectType' => SubjectType::KEYWORD, 'subjectCode' => $subject];
    }
}

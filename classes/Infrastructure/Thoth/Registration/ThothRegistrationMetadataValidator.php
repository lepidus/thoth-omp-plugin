<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Registration;

use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalServiceFailure;
use APP\plugins\generic\thoth\classes\Application\Registration\Port\RegistrationMetadataValidator;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use Biblys\Isbn\Isbn;
use Biblys\Isbn\IsbnParsingException;
use Biblys\Isbn\IsbnValidationException;
use ThothApi\Exception\QueryException;

final class ThothRegistrationMetadataValidator implements RegistrationMetadataValidator
{
    public function __construct(
        private readonly object $workMetadataReader,
        private readonly object $publicationFormatDao,
        private readonly ThothRemoteGateway $remote
    ) {
    }

    public function validate(object $publication): array
    {
        $errors = [];
        $work = $this->workMetadataReader->fromPublication($publication);
        $doi = trim((string) ($work['doi'] ?? ''));
        if ($doi !== '' && $this->doiExists($doi)) {
            $errors[] = __('plugins.generic.thoth.validation.doiExists', ['doi' => $doi]);
        }

        $landingPage = trim((string) ($work['landingPage'] ?? ''));
        if ($landingPage !== '') {
            $matches = $this->remote->call('registrationValidation', 'works', [
                1,
                0,
                $landingPage,
                null,
                null,
                null,
                null,
                null,
                ['landingPage'],
            ]);
            foreach ($matches ?? [] as $match) {
                if ((string) $match->getLandingPage() === $landingPage) {
                    $errors[] = __(
                        'plugins.generic.thoth.validation.landingPageExists',
                        ['landingPage' => $landingPage]
                    );
                    break;
                }
            }
        }

        foreach ($this->publicationFormatDao->getByPublicationId($publication->getId()) as $format) {
            $isbn = $this->isbn($format);
            if ($isbn === null) {
                continue;
            }
            try {
                Isbn::validateAsIsbn13($isbn);
            } catch (IsbnParsingException | IsbnValidationException) {
                $errors[] = __('plugins.generic.thoth.validation.isbn', [
                    'isbn' => $isbn,
                    'formatName' => $format->getLocalizedName(),
                ]);
            }
            $matches = $this->remote->call('registrationValidation', 'publications', [
                1,
                0,
                $isbn,
                null,
                null,
                null,
                ['isbn'],
            ]);
            if (($matches ?? []) !== []) {
                $errors[] = __('plugins.generic.thoth.validation.isbnExists', ['isbn' => $isbn]);
            }
        }

        return $errors;
    }

    private function doiExists(string $doi): bool
    {
        try {
            return $this->remote->call('registrationValidation', 'workByDoi', [$doi, ['workId']]) !== null;
        } catch (ExternalServiceFailure $failure) {
            $previous = $failure->getPrevious();
            if (
                $previous instanceof QueryException
                && $previous->getStatusCode() === 200
                && rtrim($previous->getMessage(), '.') === 'No record was found for the given ID'
            ) {
                return false;
            }

            throw $failure;
        }
    }

    private function isbn(object $format): ?string
    {
        foreach ($format->getIdentificationCodes()->toArray() as $code) {
            if (in_array((string) $code->getCode(), ['15', '24'], true)) {
                $isbn = trim((string) $code->getValue());
                return $isbn === '' ? null : $isbn;
            }
        }

        return null;
    }
}

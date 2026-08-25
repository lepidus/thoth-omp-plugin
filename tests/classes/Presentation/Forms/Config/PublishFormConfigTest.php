<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Forms\Config;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Application\FailureReporting\ThothUnavailable;
use APP\plugins\generic\thoth\classes\Application\Registration\Port\PublisherAccessGateway;
use APP\plugins\generic\thoth\classes\Application\Registration\Port\RegistrationMetadataValidator;
use APP\plugins\generic\thoth\classes\Application\Submission\Port\SubmissionReader;
use APP\plugins\generic\thoth\classes\Domain\Imprint\Imprint;
use APP\plugins\generic\thoth\classes\Domain\Imprint\ImprintId;
use APP\plugins\generic\thoth\classes\Domain\Registration\BookWorkType;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use APP\plugins\generic\thoth\classes\Presentation\Forms\Config\PublishFormConfig;
use APP\submission\Submission;
use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;
use PKP\tests\PKPTestCase;

final class PublishFormConfigTest extends PKPTestCase
{
    private const IMPRINT_ID = 'f740cf4e-16d1-487c-9a92-615882a591e9';

    public function testAddsRegistrationFieldsForAnEligibleAuthoredWork(): void
    {
        $form = new PublishFormDouble();
        $config = new PublishFormConfig(
            new SubmissionReaderDouble($this->submission(null, Submission::WORK_TYPE_AUTHORED_WORK)),
            new RegistrationMetadataValidatorDouble([]),
            new PublishPublisherAccessGatewayDouble(true, [
                new Imprint(new ImprintId(self::IMPRINT_ID), 'Example Press'),
            ])
        );

        $result = $config->addConfig('Form::config::before', $form);

        $this->assertFalse($result);
        $this->assertInstanceOf(FieldOptions::class, $form->fields['registerConfirmation']);
        $this->assertInstanceOf(FieldSelect::class, $form->fields['thothImprintId']);
        $this->assertSame(self::IMPRINT_ID, $form->fields['thothImprintId']->value);
        $this->assertSame(
            [BookWorkType::MONOGRAPH, BookWorkType::TEXTBOOK],
            array_column($form->fields['thothWorkType']->options, 'value')
        );
    }

    public function testShowsValidationErrorsWithoutRegistrationFields(): void
    {
        $form = new PublishFormDouble();
        $config = new PublishFormConfig(
            new SubmissionReaderDouble($this->submission(null, Submission::WORK_TYPE_EDITED_VOLUME)),
            new RegistrationMetadataValidatorDouble(['Invalid <script>alert(1)</script>']),
            new PublishPublisherAccessGatewayDouble(true)
        );

        $config->addConfig('Form::config::before', $form);

        $this->assertInstanceOf(FieldHTML::class, $form->fields['registerNotice']);
        $this->assertStringNotContainsString('<script>', $form->fields['registerNotice']->description);
        $this->assertArrayNotHasKey('registerConfirmation', $form->fields);
    }

    public function testRemoteFailureShowsConnectionWarningWithoutAbortingFormConfig(): void
    {
        $form = new PublishFormDouble();
        $config = new PublishFormConfig(
            new SubmissionReaderDouble($this->submission(null, Submission::WORK_TYPE_EDITED_VOLUME)),
            new RegistrationMetadataValidatorDouble([], new ThothUnavailable('registrationValidation', null)),
            new PublishPublisherAccessGatewayDouble(true)
        );

        $result = $config->addConfig('Form::config::before', $form);

        $this->assertFalse($result);
        $this->assertInstanceOf(FieldHTML::class, $form->fields['registerNotice']);
    }

    public function testDoesNotOfferRegistrationForAnAlreadyLinkedSubmission(): void
    {
        $form = new PublishFormDouble();
        $validator = new RegistrationMetadataValidatorDouble([]);
        $config = new PublishFormConfig(
            new SubmissionReaderDouble($this->submission('work-id', Submission::WORK_TYPE_EDITED_VOLUME)),
            $validator,
            new PublishPublisherAccessGatewayDouble(true)
        );

        $config->addConfig('Form::config::before', $form);

        $this->assertSame([], $form->fields);
        $this->assertSame(0, $validator->calls);
    }

    private function submission(?string $workId, int $workType): object
    {
        return new class ($workId, $workType) {
            public function __construct(private ?string $workId, private int $workType)
            {
            }

            public function getData(string $key)
            {
                return match ($key) {
                    'thothWorkId' => $this->workId,
                    'workType' => $this->workType,
                    default => null,
                };
            }
        };
    }
}

final class SubmissionReaderDouble implements SubmissionReader
{
    public function __construct(private ?object $submission)
    {
    }

    public function find(SubmissionId $submissionId): ?object
    {
        return $this->submission;
    }
}

final class RegistrationMetadataValidatorDouble implements RegistrationMetadataValidator
{
    public int $calls = 0;

    public function __construct(private array $errors, private ?\Throwable $failure = null)
    {
    }

    public function validate(object $publication): array
    {
        $this->calls++;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->errors;
    }
}

final class PublishPublisherAccessGatewayDouble implements PublisherAccessGateway
{
    public function __construct(private bool $canUploadFiles, private array $imprints = [])
    {
    }

    public function canUploadFiles(): bool
    {
        return $this->canUploadFiles;
    }

    public function imprints(): array
    {
        return $this->imprints;
    }
}

final class PublishFormDouble
{
    public string $id = 'publish';
    public array $errors = [];
    public array $fields = [];
    public object $publication;

    public function __construct()
    {
        $this->publication = new class () {
            public function getData(string $key): ?int
            {
                return $key === 'submissionId' ? 1 : null;
            }
        };
    }

    public function addField(object $field): self
    {
        $this->fields[$field->name] = $field;

        return $this;
    }
}

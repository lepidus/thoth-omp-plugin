<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;

import('lib.pkp.tests.PKPTestCase');
import('classes.submission.Submission');

final class PublishFormConfigTest extends PKPTestCase
{
    private const IMPRINT_ID = 'f740cf4e-16d1-487c-9a92-615882a591e9';

    public function testAddsRegistrationFieldsForAnEligibleAuthoredWork(): void
    {
        $form = new PublishFormDouble();
        $config = new PublishFormConfig(
            new SubmissionReaderDouble($this->submission(null, WORK_TYPE_AUTHORED_WORK)),
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
            new SubmissionReaderDouble($this->submission(null, WORK_TYPE_EDITED_VOLUME)),
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
            new SubmissionReaderDouble($this->submission(null, WORK_TYPE_EDITED_VOLUME)),
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
            new SubmissionReaderDouble($this->submission('work-id', WORK_TYPE_EDITED_VOLUME)),
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
            private ?string $workId;
            private int $workType;
            public function __construct(?string $workId, int $workType)
            {
                $this->workId = $workId;
                $this->workType = $workType;
            }

            public function getData(string $key)
            {
                if ($key === 'thothWorkId') {
                    return $this->workId;
                }
                if ($key === 'workType') {
                    return $this->workType;
                }
                return null;
            }
        };
    }
}

final class SubmissionReaderDouble implements SubmissionReader
{
    private ?object $submission;
    public function __construct(?object $submission)
    {
        $this->submission = $submission;
    }

    public function find(SubmissionId $submissionId): ?object
    {
        return $this->submission;
    }
}

final class RegistrationMetadataValidatorDouble implements RegistrationMetadataValidator
{
    public int $calls = 0;

    private array $errors;
    private ?\Throwable $failure;
    public function __construct(array $errors, ?\Throwable $failure = null)
    {
        $this->errors = $errors;
        $this->failure = $failure;
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
    private bool $canUploadFiles;
    private array $imprints;
    public function __construct(bool $canUploadFiles, array $imprints = [])
    {
        $this->canUploadFiles = $canUploadFiles;
        $this->imprints = $imprints;
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

<?php

use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;

import('classes.submission.Submission');

final class PublishFormConfig
{
    private SubmissionReader $submissionReader;
    private RegistrationMetadataValidator $metadataValidator;
    private PublisherAccessGateway $publisherAccess;
    public function __construct(
        SubmissionReader $submissionReader,
        RegistrationMetadataValidator $metadataValidator,
        PublisherAccessGateway $publisherAccess
    ) {
        $this->submissionReader = $submissionReader;
        $this->metadataValidator = $metadataValidator;
        $this->publisherAccess = $publisherAccess;
    }

    public function addConfig(string $hookName, object $form): bool
    {
        if ($form->id !== 'publish' || $form->errors !== []) {
            return false;
        }

        $publication = $form->publication;
        $submissionId = (int) $publication->getData('submissionId');
        if ($submissionId <= 0) {
            return false;
        }
        $submission = $this->submissionReader->find(new SubmissionId($submissionId));
        if ($submission === null || $submission->getData('thothWorkId')) {
            return false;
        }

        try {
            $errors = $this->metadataValidator->validate($publication);
            $imprints = $errors === [] ? $this->publisherAccess->imprints() : [];
        } catch (ExternalServiceFailure $failure) {
            error_log($failure->getMessage());
            $errors = [__('plugins.generic.thoth.connectionError')];
            $imprints = [];
        }

        if ($errors !== []) {
            $form->addField(new FieldHTML('registerNotice', [
                'description' => ThothValidationMessageFormatter::formatWarning($errors),
                'groupId' => 'default',
            ]));
            return false;
        }

        $imprintOptions = array_map(
            fn (Imprint $imprint): array => [
                'value' => $imprint->id()->toString(),
                'label' => $imprint->name(),
            ],
            $imprints
        );
        $form->addField(new FieldOptions('registerConfirmation', [
            'label' => __('plugins.generic.thoth.register.label'),
            'options' => [['value' => true, 'label' => __('plugins.generic.thoth.register.confirmation')]],
            'value' => false,
            'groupId' => 'default',
        ]))->addField(new FieldSelect('thothImprintId', [
            'label' => __('plugins.generic.thoth.imprint'),
            'options' => $imprintOptions,
            'isRequired' => true,
            'showWhen' => 'registerConfirmation',
            'groupId' => 'default',
            'value' => $imprintOptions[0]['value'] ?? null,
        ]));

        if ($submission->getData('workType') === WORK_TYPE_AUTHORED_WORK) {
            $workTypeOptions = [
                ['value' => BookWorkType::MONOGRAPH, 'label' => __('plugins.generic.thoth.workType.monograph')],
                ['value' => BookWorkType::TEXTBOOK, 'label' => __('plugins.generic.thoth.workType.textbook')],
            ];
            $form->addField(new FieldSelect('thothWorkType', [
                'label' => __('plugins.generic.thoth.workType'),
                'options' => $workTypeOptions,
                'isRequired' => true,
                'showWhen' => 'registerConfirmation',
                'groupId' => 'default',
                'value' => $workTypeOptions[0]['value'],
            ]));
        }

        return false;
    }
}

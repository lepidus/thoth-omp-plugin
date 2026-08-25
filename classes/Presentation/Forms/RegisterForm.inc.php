<?php

use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FormComponent;

import('classes.submission.Submission');

final class RegisterForm extends FormComponent
{
    public function __construct(string $action, array $imprints, int $workType, array $errors)
    {
        parent::__construct('register', 'PUT', $action, []);

        if ($errors !== []) {
            $this->addPage(['id' => 'default'])->addGroup([
                'id' => 'default',
                'pageId' => 'default',
            ]);
            $this->addField(new FieldHTML('registerNotice', [
                'description' => ThothValidationMessageFormatter::formatWarning($errors),
                'groupId' => 'default',
            ]));
            return;
        }

        $this->addPage([
            'id' => 'default',
            'submitButton' => ['label' => __('plugins.generic.thoth.register')],
        ])->addGroup([
            'id' => 'default',
            'pageId' => 'default',
        ]);

        $imprintOptions = array_map(
            fn (Imprint $imprint): array => [
                'value' => $imprint->id()->toString(),
                'label' => $imprint->name(),
            ],
            $imprints
        );

        $this->addField(new FieldHTML('validation', [
            'description' => __('plugins.generic.thoth.register.confirmation'),
            'groupId' => 'default',
        ]))->addField(new FieldSelect('thothImprintId', [
            'label' => __('plugins.generic.thoth.imprint'),
            'options' => $imprintOptions,
            'isRequired' => true,
            'groupId' => 'default',
            'value' => $imprintOptions[0]['value'] ?? null,
        ]));

        if ($workType === WORK_TYPE_AUTHORED_WORK) {
            $workTypeOptions = [
                ['value' => BookWorkType::MONOGRAPH, 'label' => __('plugins.generic.thoth.workType.monograph')],
                ['value' => BookWorkType::TEXTBOOK, 'label' => __('plugins.generic.thoth.workType.textbook')],
            ];
            $this->addField(new FieldSelect('thothWorkType', [
                'label' => __('plugins.generic.thoth.workType'),
                'options' => $workTypeOptions,
                'isRequired' => true,
                'groupId' => 'default',
                'value' => $workTypeOptions[0]['value'],
            ]));
        }
    }
}

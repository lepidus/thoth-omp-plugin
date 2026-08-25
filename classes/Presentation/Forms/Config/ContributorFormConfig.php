<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Forms\Config;

use PKP\components\forms\FieldOptions;

final class ContributorFormConfig
{
    public function addConfig(string $hookName, object $form): bool
    {
        if ($form->id !== 'contributor' || $form->errors !== []) {
            return false;
        }

        $form->addField(new FieldOptions('mainContribution', [
            'label' => __('plugins.generic.thoth.field.mainContribution'),
            'value' => false,
            'options' => [[
                'value' => true,
                'label' => __('plugins.generic.thoth.field.mainContribution.label'),
            ]],
        ]));

        return false;
    }
}

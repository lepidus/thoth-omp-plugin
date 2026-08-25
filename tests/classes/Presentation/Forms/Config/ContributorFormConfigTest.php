<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use PKP\components\forms\FieldOptions;

import('lib.pkp.tests.PKPTestCase');

final class ContributorFormConfigTest extends PKPTestCase
{
    public function testAddsMainContributionToTheContributorForm(): void
    {
        $form = new ContributorFormDouble();

        $result = (new ContributorFormConfig())->addConfig('Form::config::before', $form);

        $this->assertFalse($result);
        $this->assertInstanceOf(FieldOptions::class, $form->fields['mainContribution']);
        $this->assertFalse($form->fields['mainContribution']->value);
    }

    public function testLeavesOtherOrInvalidFormsUnchanged(): void
    {
        $other = new ContributorFormDouble();
        $other->id = 'publish';
        $invalid = new ContributorFormDouble();
        $invalid->errors = ['invalid'];
        $config = new ContributorFormConfig();

        $this->assertFalse($config->addConfig('Form::config::before', $other));
        $this->assertFalse($config->addConfig('Form::config::before', $invalid));
        $this->assertSame([], $other->fields);
        $this->assertSame([], $invalid->fields);
    }
}

final class ContributorFormDouble
{
    public string $id = 'contributor';
    public array $errors = [];
    public array $fields = [];

    public function addField(object $field): self
    {
        $this->fields[$field->name] = $field;

        return $this;
    }
}

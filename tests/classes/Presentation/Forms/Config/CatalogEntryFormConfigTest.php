<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use PKP\components\forms\FieldOptions;

import('lib.pkp.tests.PKPTestCase');

final class CatalogEntryFormConfigTest extends PKPTestCase
{
    public function testAddsEnabledFrontcoverFieldAfterTheOmpCoverField(): void
    {
        $form = new CatalogEntryFormDouble();
        $config = new CatalogEntryFormConfig(
            new PublicationReaderDouble($this->publication(true)),
            new CatalogPublisherAccessGatewayDouble(true)
        );

        $result = $config->addConfig('Form::config::before', $form);

        $field = $form->fields['thothUploadFrontcover'];
        $this->assertFalse($result);
        $this->assertInstanceOf(FieldOptions::class, $field);
        $this->assertSame(['after', 'coverImage'], $form->positions['thothUploadFrontcover']);
        $this->assertTrue($field->value);
        $this->assertFalse($field->options[0]['disabled']);
        $this->assertSame('image/*', $form->getField('coverImage')->options['acceptedFiles']);
    }

    public function testDisablesFrontcoverFieldWithoutCdnWritePermission(): void
    {
        $form = new CatalogEntryFormDouble();
        $config = new CatalogEntryFormConfig(
            new PublicationReaderDouble($this->publication(true)),
            new CatalogPublisherAccessGatewayDouble(false)
        );

        $config->addConfig('Form::config::before', $form);

        $field = $form->fields['thothUploadFrontcover'];
        $this->assertFalse($field->value);
        $this->assertTrue($field->options[0]['disabled']);
    }

    public function testLeavesAnotherFormAndAMissingPublicationUnchanged(): void
    {
        $other = new CatalogEntryFormDouble();
        $other->id = 'submission';
        $config = new CatalogEntryFormConfig(
            new PublicationReaderDouble(null),
            new CatalogPublisherAccessGatewayDouble(true)
        );

        $this->assertFalse($config->addConfig('Form::config::before', $other));
        $this->assertCount(1, $other->fields);

        $catalog = new CatalogEntryFormDouble();
        $this->assertFalse($config->addConfig('Form::config::before', $catalog));
        $this->assertCount(1, $catalog->fields);
    }

    private function publication(bool $uploadFrontcover): object
    {
        return new class ($uploadFrontcover) {
            private bool $uploadFrontcover;
            public function __construct(bool $uploadFrontcover)
            {
                $this->uploadFrontcover = $uploadFrontcover;
            }

            public function getData(string $key)
            {
                return [
                    'place' => null,
                    'pageCount' => null,
                    'imageCount' => null,
                    'thothUploadFrontcover' => $this->uploadFrontcover,
                ][$key] ?? null;
            }
        };
    }
}

final class PublicationReaderDouble implements PublicationReader
{
    private ?object $publication;
    public function __construct(?object $publication)
    {
        $this->publication = $publication;
    }

    public function find(PublicationId $publicationId): ?object
    {
        return $this->publication;
    }
}

final class CatalogPublisherAccessGatewayDouble implements PublisherAccessGateway
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

final class CatalogEntryFormDouble
{
    public string $id = 'catalogEntry';
    public array $errors = [];
    public string $action = 'https://example.test/publications/1';
    public array $fields = [];
    public array $positions = [];

    public function __construct()
    {
        $this->fields['coverImage'] = (object) [
            'name' => 'coverImage',
            'options' => ['acceptedFiles' => 'image/*'],
        ];
    }

    public function addField(object $field, array $position = []): self
    {
        $this->fields[$field->name] = $field;
        $this->positions[$field->name] = $position;

        return $this;
    }

    public function getField(string $fieldName): ?object
    {
        return $this->fields[$fieldName] ?? null;
    }
}

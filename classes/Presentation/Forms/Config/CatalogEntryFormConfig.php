<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Forms\Config;

use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalServiceFailure;
use APP\plugins\generic\thoth\classes\Application\Publication\Port\PublicationReader;
use APP\plugins\generic\thoth\classes\Application\Registration\Port\PublisherAccessGateway;
use APP\plugins\generic\thoth\classes\Domain\Publication\PublicationId;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldText;

final class CatalogEntryFormConfig
{
    public function __construct(
        private PublicationReader $publicationReader,
        private PublisherAccessGateway $publisherAccess
    ) {
    }

    public function addConfig(string $hookName, object $form): bool
    {
        if ($form->id !== 'catalogEntry' || $form->errors !== []) {
            return false;
        }

        $publicationId = basename(parse_url($form->action, PHP_URL_PATH) ?? '');
        if (!ctype_digit($publicationId) || (int) $publicationId <= 0) {
            return false;
        }
        $publication = $this->publicationReader->find(new PublicationId((int) $publicationId));
        if ($publication === null) {
            return false;
        }

        $form->addField(new FieldText('place', [
            'label' => __('plugins.generic.thoth.field.place.label'),
            'value' => $publication->getData('place'),
        ]))->addField(new FieldText('pageCount', [
            'label' => __('plugins.generic.thoth.field.pageCount.label'),
            'value' => $publication->getData('pageCount'),
        ]))->addField(new FieldText('imageCount', [
            'label' => __('plugins.generic.thoth.field.imageCount.label'),
            'value' => $publication->getData('imageCount'),
        ]));

        try {
            $canUploadFiles = $this->publisherAccess->canUploadFiles();
        } catch (ExternalServiceFailure $failure) {
            error_log($failure->getMessage());
            $canUploadFiles = false;
        }

        $form->addField(new FieldOptions('thothUploadFrontcover', [
            'label' => __('plugins.generic.thoth.field.frontcover'),
            'description' => $canUploadFiles
                ? null
                : __('plugins.generic.thoth.field.frontcover.missingCdnWritePermission'),
            'options' => [[
                'value' => true,
                'label' => __('plugins.generic.thoth.field.frontcover.label'),
                'disabled' => !$canUploadFiles,
            ]],
            'value' => (bool) $publication->getData('thothUploadFrontcover') && $canUploadFiles,
        ]), ['after', 'coverImage']);

        return false;
    }
}

<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Schema;

final class ThothSchema
{
    public function addWorkIdToSchema(string $hookName, array $args): bool
    {
        $schema = &$args[0];
        $schema->properties->thothWorkId = $this->property('string');
        return false;
    }

    public function addReasonToSchema(string $hookName, array $args): bool
    {
        $schema = &$args[0];
        $schema->properties->reason = $this->property('string');
        return false;
    }

    public function addToPublicationSchema(string $hookName, array $args): bool
    {
        $schema = &$args[0];
        $schema->properties->place = $this->property('string');
        $schema->properties->pageCount = $this->property('integer');
        $schema->properties->imageCount = $this->property('integer');
        $schema->properties->thothUploadFrontcover = $this->property('boolean');
        $schema->properties->thothFrontcoverSha256 = $this->property('string');
        $schema->properties->thothFrontcoverUrl = $this->property('string');
        return false;
    }

    public function addToAuthorSchema(string $hookName, array $args): bool
    {
        $schema = &$args[0];
        $schema->properties->mainContribution = $this->property('boolean');
        return false;
    }

    public function addToSubmissionsListProps(string $hookName, array $args): bool
    {
        $props = &$args[0];
        $props[] = 'thothWorkId';
        return false;
    }

    private function property(string $type): object
    {
        return (object) [
            'type' => $type,
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
    }
}

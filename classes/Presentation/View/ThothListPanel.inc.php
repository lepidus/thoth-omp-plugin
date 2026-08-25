<?php

use PKP\components\listPanels\ListPanel;

import('lib.pkp.classes.submission.PKPSubmission');

final class ThothListPanel extends ListPanel
{
    public string $apiUrl = '';
    public int $count = 30;
    public array $getParams = [];
    public int $itemsMax = 0;
    public array $imprintOptions = [];
    public ?string $selectedImprint = null;
    public string $csrfToken = '';
    public ?int $contextId = null;
    public array $filters = [];
    public $isSidebarVisible = true;

    public function __construct(string $id, string $title, array $args = [])
    {
        $catalogFilters = $args['filters'] ?? [];
        $args['filters'] = array_merge([$this->statusFilter()], $catalogFilters);
        parent::__construct($id, $title, $args);
    }

    public function getConfig(): array
    {
        $config = parent::getConfig();
        $config['apiUrl'] = $this->apiUrl;
        $config['count'] = $this->count;
        $config['getParams'] = $this->getParams;
        $config['itemsMax'] = $this->itemsMax;
        $config['imprintOptions'] = $this->imprintOptions;
        $config['selectedImprint'] = $this->selectedImprint ?? '';
        $config['csrfToken'] = $this->csrfToken;
        $config['filters'] = $this->filters;

        if ($this->contextId !== null) {
            $config['contextId'] = $this->contextId;
        }

        return $config;
    }

    private function statusFilter(): array
    {
        return [
            'heading' => __('common.status'),
            'filters' => [
                [
                    'param' => 'status',
                    'value' => STATUS_PUBLISHED,
                    'title' => __('publication.status.published'),
                ],
                [
                    'param' => 'status',
                    'value' => STATUS_QUEUED,
                    'title' => __('publication.status.unpublished'),
                ],
            ],
        ];
    }
}

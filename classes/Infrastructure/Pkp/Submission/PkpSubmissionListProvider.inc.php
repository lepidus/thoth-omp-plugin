<?php

final class PkpSubmissionListProvider implements SubmissionListProvider
{
    private object $submissionService;
    private object $categoryDao;
    private object $seriesDao;
    private object $request;

    public function __construct(
        object $submissionService,
        object $categoryDao,
        object $seriesDao,
        object $request
    ) {
        $this->submissionService = $submissionService;
        $this->categoryDao = $categoryDao;
        $this->seriesDao = $seriesDao;
        $this->request = $request;
    }

    public function get(int $contextId, ?int $assignedUserId, int $limit): array
    {
        $params = ['contextId' => $contextId, 'count' => $limit];
        if ($assignedUserId !== null) {
            $params['assignedTo'] = [$assignedUserId];
        }
        $items = [];
        foreach ($this->submissionService->getMany($params) as $submission) {
            $items[] = $this->submissionService->getBackendListProperties(
                $submission,
                ['request' => $this->request]
            );
        }

        return [
            'items' => $items,
            'itemsMax' => $this->submissionService->getMax($params),
            'filters' => $this->filters($contextId),
        ];
    }

    private function filters(int $contextId): array
    {
        $filters = [];
        $categories = $this->catalogFilters(
            $this->categoryDao,
            $contextId,
            'categoryIds'
        );
        if ($categories !== []) {
            $filters[] = ['heading' => __('catalog.categories'), 'filters' => $categories];
        }
        $series = $this->catalogFilters($this->seriesDao, $contextId, 'seriesIds');
        if ($series !== []) {
            $filters[] = ['heading' => __('catalog.manage.series'), 'filters' => $series];
        }

        return $filters;
    }

    private function catalogFilters(object $dao, int $contextId, string $parameter): array
    {
        $filters = [];
        foreach ($this->items($dao->getByContextId($contextId)) as $item) {
            [$sortBy, $sortDirection] = array_pad(explode('-', (string) $item->getSortOption(), 2), 2, '');
            $filters[] = [
                'param' => $parameter,
                'value' => (int) $item->getId(),
                'title' => $item->getLocalizedTitle(),
                'sortBy' => $sortBy,
                'sortDir' => strtolower($sortDirection) === 'desc' ? 'DESC' : 'ASC',
            ];
        }

        return $filters;
    }

    private function items($result): array
    {
        if (is_array($result)) {
            return array_values($result);
        }
        if (is_object($result) && method_exists($result, 'toArray')) {
            return array_values($result->toArray());
        }

        return is_iterable($result) ? array_values(iterator_to_array($result)) : [];
    }
}

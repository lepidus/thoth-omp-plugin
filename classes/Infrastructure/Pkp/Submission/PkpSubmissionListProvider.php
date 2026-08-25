<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Submission;

use APP\plugins\generic\thoth\classes\Application\Submission\Port\SubmissionListProvider;
use Closure;
use PKP\db\DAO;

final class PkpSubmissionListProvider implements SubmissionListProvider
{
    private Closure $userGroups;

    public function __construct(
        private readonly object $submissionRepository,
        private readonly object $categoryRepository,
        private readonly object $sectionRepository,
        private readonly object $genreDao,
        callable $userGroups,
        private readonly array $userRoles
    ) {
        $this->userGroups = Closure::fromCallable($userGroups);
    }

    public function get(int $contextId, ?int $assignedUserId, int $limit): array
    {
        $collector = $this->submissionRepository->getCollector()->filterByContextIds([$contextId]);
        if ($assignedUserId !== null) {
            $collector->assignedTo([$assignedUserId]);
        }
        $count = $collector->getCount();
        $submissions = $collector->limit($limit)->getMany();
        $userGroups = ($this->userGroups)($contextId);
        $genres = $this->items($this->genreDao->getByContextId($contextId));
        $mapped = $this->submissionRepository->getSchemaMap()->mapMany(
            $submissions,
            $userGroups,
            $genres,
            $this->userRoles
        );

        return [
            'items' => is_object($mapped) && method_exists($mapped, 'values')
                ? $mapped->values()->all()
                : array_values((array) $mapped),
            'itemsMax' => $count,
            'filters' => $this->filters($contextId),
        ];
    }

    private function filters(int $contextId): array
    {
        $filters = [];
        $categories = $this->catalogFilters(
            $this->categoryRepository,
            $contextId,
            'categoryIds'
        );
        if ($categories !== []) {
            $filters[] = ['heading' => __('catalog.categories'), 'filters' => $categories];
        }
        $series = $this->catalogFilters($this->sectionRepository, $contextId, 'seriesIds');
        if ($series !== []) {
            $filters[] = ['heading' => __('catalog.manage.series'), 'filters' => $series];
        }

        return $filters;
    }

    private function catalogFilters(object $repository, int $contextId, string $parameter): array
    {
        $filters = [];
        foreach ($repository->getCollector()->filterByContextIds([$contextId])->getMany() as $item) {
            [$sortBy, $sortDirection] = array_pad(explode('-', (string) $item->getSortOption(), 2), 2, '');
            $filters[] = [
                'param' => $parameter,
                'value' => (int) $item->getId(),
                'title' => $item->getLocalizedTitle(),
                'sortBy' => $sortBy,
                'sortDir' => $sortDirection === DAO::SORT_DIRECTION_DESC ? 'DESC' : 'ASC',
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

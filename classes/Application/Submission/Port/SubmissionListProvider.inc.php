<?php

interface SubmissionListProvider
{
    /**
     * @return array{items: array, itemsMax: int, filters: array}
     */
    public function get(int $contextId, ?int $assignedUserId, int $limit): array;
}

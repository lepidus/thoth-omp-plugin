<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Work;

use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalServiceFailure;
use APP\plugins\generic\thoth\classes\Application\Work\Port\WorkGateway;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use ThothApi\Exception\QueryException;

final class ThothWorkGateway implements WorkGateway
{
    private const NOT_FOUND_MESSAGE = 'No record was found for the given ID';
    private const WORK_SELECTION = ['workStatus'];

    private ThothRemoteGateway $remote;

    public function __construct(ThothRemoteGateway $remote)
    {
        $this->remote = $remote;
    }

    public function getStatus(WorkId $workId): ?string
    {
        try {
            $work = $this->remote->call(
                'workStatus',
                'work',
                [$workId->toString(), self::WORK_SELECTION]
            );
        } catch (ExternalServiceFailure $failure) {
            if ($this->isMissingWork($failure->getPrevious())) {
                return null;
            }

            throw $failure;
        }

        return $work->getWorkStatus();
    }

    private function isMissingWork(?\Throwable $failure): bool
    {
        return $failure instanceof QueryException
            && $failure->getStatusCode() === 200
            && rtrim($failure->getMessage(), '.') === self::NOT_FOUND_MESSAGE;
    }
}

<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Api;

use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use ThothApi\Exception\QueryException;

final class GetWorkStatusController
{
    private GetWorkStatus $getWorkStatus;

    public function __construct(GetWorkStatus $getWorkStatus)
    {
        $this->getWorkStatus = $getWorkStatus;
    }

    public function get(object $submission, object $response): object
    {
        $thothWorkId = $submission->getData('thothWorkId');
        if (!$thothWorkId) {
            return $response->withStatus(404)->withJsonError('plugins.generic.thoth.status.unregistered');
        }

        try {
            $workStatus = $this->getWorkStatus->execute(new WorkId($thothWorkId));
            if ($workStatus === null) {
                return $response->withStatus(404)->withJson([
                    'error' => __('plugins.generic.thoth.status.notFound'),
                    'workNotFound' => true,
                ]);
            }

            return $response->withJson(['workStatus' => $workStatus], 200);
        } catch (QueryException $exception) {
            return $response->withStatus(500)->withJsonError('plugins.generic.thoth.connectionError');
        }
    }
}

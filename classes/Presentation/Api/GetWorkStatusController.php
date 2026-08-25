<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Api;

use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;

final class GetWorkStatusController
{
    public function __construct(private GetWorkStatus $getWorkStatus)
    {
    }

    public function get(object $submission): JsonResponse
    {
        $thothWorkId = $submission->getData('thothWorkId');
        if (!$thothWorkId) {
            return new JsonResponse(
                ['error' => __('plugins.generic.thoth.status.unregistered')],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $workStatus = $this->getWorkStatus->execute(new WorkId($thothWorkId));
            if ($workStatus === null) {
                return new JsonResponse(
                    [
                        'error' => __('plugins.generic.thoth.status.notFound'),
                        'workNotFound' => true,
                    ],
                    Response::HTTP_NOT_FOUND
                );
            }

            return new JsonResponse(['workStatus' => $workStatus], Response::HTTP_OK);
        } catch (Throwable $exception) {
            return new JsonResponse(
                ['error' => __('plugins.generic.thoth.connectionError')],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}

<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Api;

use APP\plugins\generic\thoth\classes\Application\Work\UnlinkWork;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class UnlinkWorkController
{
    public function __construct(private UnlinkWork $unlinkWork)
    {
    }

    public function delete(object $submission): JsonResponse
    {
        $thothWorkId = $submission->getData('thothWorkId');
        if (!$thothWorkId) {
            return new JsonResponse(
                ['error' => __('plugins.generic.thoth.status.unregistered')],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $unlinked = $this->unlinkWork->execute(
                new SubmissionId($submission->getId()),
                new WorkId($thothWorkId)
            );
            if (!$unlinked) {
                return new JsonResponse(
                    ['error' => __('plugins.generic.thoth.unlink.existingWork')],
                    Response::HTTP_CONFLICT
                );
            }
        } catch (Exception $exception) {
            return new JsonResponse(
                ['error' => __('plugins.generic.thoth.connectionError')],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new JsonResponse(['status' => true], Response::HTTP_OK);
    }
}

<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Api;

use APP\plugins\generic\thoth\classes\Application\Work\UnlinkWork;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class UnlinkWorkController
{
    public function __construct(private readonly UnlinkWork $unlinkWork)
    {
    }

    public function delete(object $submission): JsonResponse
    {
        $thothWorkId = $submission->getData('thothWorkId');
        if (!$thothWorkId) {
            return response()->json(
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
                return response()->json(
                    ['error' => __('plugins.generic.thoth.unlink.existingWork')],
                    Response::HTTP_CONFLICT
                );
            }
        } catch (Exception $exception) {
            return response()->json(
                ['error' => __('plugins.generic.thoth.connectionError')],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return response()->json(['status' => true], Response::HTTP_OK);
    }
}

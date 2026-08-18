<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Api;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadFeatureVideo;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Throwable;

final class UploadFeatureVideoController
{
    public function __construct(private readonly UploadFeatureVideo $uploadFeatureVideo)
    {
    }

    public function upload(object $submission, string $title, int $temporaryFileId, int $userId): JsonResponse
    {
        try {
            $metadata = $this->uploadFeatureVideo->execute(
                new WorkId($submission->getData('thothWorkId')),
                $title,
                $temporaryFileId,
                $userId
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(
                ['video' => [__('plugins.generic.thoth.featureVideo.invalidFile')]],
                Response::HTTP_BAD_REQUEST
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return response()->json(
                ['error' => __('plugins.generic.thoth.connectionError')],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return response()->json($metadata, Response::HTTP_OK);
    }
}

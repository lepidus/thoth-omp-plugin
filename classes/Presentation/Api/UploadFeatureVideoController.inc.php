<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class UploadFeatureVideoController
{
    private UploadFeatureVideo $uploadFeatureVideo;
    public function __construct(UploadFeatureVideo $uploadFeatureVideo)
    {
        $this->uploadFeatureVideo = $uploadFeatureVideo;
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
            return new JsonResponse(
                ['video' => [__('plugins.generic.thoth.featureVideo.invalidFile')]],
                Response::HTTP_BAD_REQUEST
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return new JsonResponse(
                ['error' => __('plugins.generic.thoth.connectionError')],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new JsonResponse($metadata, Response::HTTP_OK);
    }
}

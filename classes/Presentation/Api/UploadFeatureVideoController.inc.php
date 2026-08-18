<?php

import('plugins.generic.thoth.classes.Application.HostedAssets.UploadFeatureVideo');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class UploadFeatureVideoController
{
    private UploadFeatureVideo $uploadFeatureVideo;

    public function __construct(UploadFeatureVideo $uploadFeatureVideo)
    {
        $this->uploadFeatureVideo = $uploadFeatureVideo;
    }

    public function upload(
        object $submission,
        string $title,
        int $temporaryFileId,
        int $userId,
        object $response
    ): object {
        try {
            $metadata = $this->uploadFeatureVideo->execute(
                new WorkId($submission->getData('thothWorkId')),
                $title,
                $temporaryFileId,
                $userId
            );
        } catch (InvalidArgumentException $exception) {
            return $response->withStatus(400)->withJson([
                'video' => [__('plugins.generic.thoth.featureVideo.invalidFile')],
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return $response->withStatus(500)->withJson([
                'error' => 'plugins.generic.thoth.connectionError',
                'errorMessage' => __('plugins.generic.thoth.connectionError'),
            ]);
        }

        return $response->withJson($metadata, 200);
    }
}

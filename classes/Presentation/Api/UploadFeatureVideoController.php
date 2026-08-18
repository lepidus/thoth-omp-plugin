<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Api;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadFeatureVideo;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use InvalidArgumentException;
use Throwable;

final class UploadFeatureVideoController
{
    public function __construct(private UploadFeatureVideo $uploadFeatureVideo)
    {
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

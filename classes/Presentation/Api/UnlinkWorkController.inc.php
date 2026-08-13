<?php

use ThothApi\Exception\QueryException;

import('plugins.generic.thoth.classes.Application.Work.UnlinkWork');
import('plugins.generic.thoth.classes.Domain.Identifier.SubmissionId');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class UnlinkWorkController
{
    private UnlinkWork $unlinkWork;

    public function __construct(UnlinkWork $unlinkWork)
    {
        $this->unlinkWork = $unlinkWork;
    }

    public function delete(object $submission, object $response): object
    {
        $thothWorkId = $submission->getData('thothWorkId');
        if (!$thothWorkId) {
            return $response->withStatus(404)->withJson([
                'error' => 'plugins.generic.thoth.status.unregistered',
                'errorMessage' => __('plugins.generic.thoth.status.unregistered'),
            ]);
        }

        try {
            $unlinked = $this->unlinkWork->execute(
                new SubmissionId((int) $submission->getId()),
                new WorkId($thothWorkId)
            );
            if (!$unlinked) {
                return $response->withStatus(409)->withJson([
                    'error' => 'plugins.generic.thoth.unlink.existingWork',
                    'errorMessage' => __('plugins.generic.thoth.unlink.existingWork'),
                ]);
            }
        } catch (QueryException $exception) {
            return $response->withStatus(500)->withJson([
                'error' => 'plugins.generic.thoth.connectionError',
                'errorMessage' => __('plugins.generic.thoth.connectionError'),
            ]);
        }

        return $response->withJson(['status' => true], 200);
    }
}

<?php

/**
 * @file plugins/generic/thoth/classes/listeners/PublicationEditListener.inc.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationEditListener
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Trigger actions on publication edit event
 */

namespace APP\plugins\generic\thoth\classes\listeners;

use APP\plugins\generic\thoth\classes\Application\Synchronization\UpdatePublicationAfterEdit;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use ThothApi\Exception\QueryException;

class PublicationEditListener
{
    public function __construct(
        private UpdatePublicationAfterEdit $updatePublication,
        private object $submissionRepository,
        private object $notification
    ) {
    }

    public function updateThothBook($hookName, $args)
    {
        $publication = $args[0];
        $submissionId = new SubmissionId($publication->getData('submissionId'));
        $request = $args[3];
        try {
            $result = $this->updatePublication->execute($publication, $submissionId, $args[2]);
        } catch (QueryException $e) {
            $submission = $this->submissionRepository->get($submissionId->toInt());
            $this->notification->notifyError($request, $submission, $e);
            return false;
        }

        if (!$result->wasUpdated()) {
            return false;
        }

        $submission = $this->submissionRepository->get($submissionId->toInt());
        if ($result->shouldNotifySuccess()) {
            $this->notification->notifySuccess($request, $submission);
        }
        foreach ($result->getWarnings() as $warning) {
            $this->notification->notifyWarning($request, $submission, $warning->getMessageKey());
        }

        return false;
    }
}

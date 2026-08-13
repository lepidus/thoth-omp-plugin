<?php

/**
 * @file plugins/generic/thoth/classes/listeners/PublicationPublishListener.inc.php
 *
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationPublishListener
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Trigger actions on publication publish event
 */

use ThothApi\Exception\QueryException;

import('plugins.generic.thoth.classes.Application.Registration.RegisterBook');
import('plugins.generic.thoth.classes.Domain.Identifier.ImprintId');
import('plugins.generic.thoth.classes.Domain.Identifier.SubmissionId');

class PublicationPublishListener
{
    private RegisterBook $registerBook;
    private object $request;
    private object $notification;

    public function __construct(RegisterBook $registerBook, object $request, object $notification)
    {
        $this->registerBook = $registerBook;
        $this->request = $request;
        $this->notification = $notification;
    }

    public function validate($hookName, $args)
    {
        $errors = & $args[0];

        $confirmation = $this->request->getUserVar('registerConfirmation');
        if (!$confirmation || $confirmation == 'false') {
            return;
        }

        $thothImprintId = $this->request->getUserVar('thothImprintId');
        if (empty($thothImprintId)) {
            $errors['thothImprintId'] = [__('plugins.generic.thoth.imprint.required')];
        }
    }

    public function registerThothBook($hookName, $args)
    {
        $publication = $args[0];
        $submission = $args[2];

        if ($submission->getData('thothWorkId')) {
            return false;
        }

        $confirmation = $this->request->getUserVar('registerConfirmation');
        if (!$confirmation || $confirmation == 'false') {
            return false;
        }

        $thothImprintId = $this->request->getUserVar('thothImprintId');
        try {
            $result = $this->registerBook->execute(
                $publication,
                new ImprintId($thothImprintId),
                new SubmissionId($submission->getId())
            );
            $this->notification->notifySuccess($this->request, $submission);
            foreach ($result->getSynchronizationResult()->getWarnings() as $warning) {
                $this->notification->notifyWarning($this->request, $submission, $warning->getMessageKey());
            }
        } catch (QueryException $e) {
            $this->notification->notifyError($this->request, $submission, $e);
        }

        return false;
    }
}

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
import('plugins.generic.thoth.classes.Domain.Registration.BookRegistrationPolicy');
import('plugins.generic.thoth.classes.facades.ThothService');

class PublicationPublishListener
{
    private RegisterBook $registerBook;
    private object $request;
    private object $notification;
    private BookRegistrationPolicy $registrationPolicy;

    public function __construct(
        RegisterBook $registerBook,
        object $request,
        object $notification,
        BookRegistrationPolicy $registrationPolicy
    ) {
        $this->registerBook = $registerBook;
        $this->request = $request;
        $this->notification = $notification;
        $this->registrationPolicy = $registrationPolicy;
    }

    public function validate($hookName, $args)
    {
        $errors = & $args[0];

        $confirmation = $this->request->getUserVar('registerConfirmation');
        $thothImprintId = $this->request->getUserVar('thothImprintId');
        $eligibility = $this->registrationPolicy->evaluate($confirmation, $thothImprintId, null, []);
        if (!$eligibility->isRequested()) {
            return;
        }

        if ($eligibility->isImprintMissing()) {
            $errors['thothImprintId'] = [__('plugins.generic.thoth.imprint.required')];
            return;
        }

        try {
            $metadataErrors = ThothService::book()->validate($args[1]);
        } catch (Exception $exception) {
            $metadataErrors = [__('plugins.generic.thoth.connectionError')];
        }
        $eligibility = $this->registrationPolicy->evaluate($confirmation, $thothImprintId, null, $metadataErrors);
        if (!$eligibility->isEligible()) {
            $errors['thothMetadata'] = $eligibility->getMetadataErrors();
        }
    }

    public function registerThothBook($hookName, $args)
    {
        $publication = $args[0];
        $submission = $args[2];

        $confirmation = $this->request->getUserVar('registerConfirmation');
        $thothImprintId = $this->request->getUserVar('thothImprintId');
        $eligibility = $this->registrationPolicy->evaluate(
            $confirmation,
            $thothImprintId,
            $submission->getData('thothWorkId'),
            []
        );
        if (!$eligibility->isEligible()) {
            return false;
        }

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

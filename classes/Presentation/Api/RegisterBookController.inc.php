<?php

import('plugins.generic.thoth.classes.Application.Exception.ExternalFailureReporter');
import('plugins.generic.thoth.classes.Application.Exception.ExternalServiceFailure');
import('plugins.generic.thoth.classes.Application.Registration.RegisterBook');
import('plugins.generic.thoth.classes.Domain.Identifier.ImprintId');
import('plugins.generic.thoth.classes.Domain.Identifier.SubmissionId');
import('plugins.generic.thoth.classes.Domain.Registration.BookRegistrationPolicy');
import('plugins.generic.thoth.classes.facades.ThothService');
import('plugins.generic.thoth.classes.notification.ThothNotification');

final class RegisterBookController
{
    private RegisterBook $registerBook;
    private BookRegistrationPolicy $registrationPolicy;
    private ExternalFailureReporter $failureReporter;

    public function __construct(
        RegisterBook $registerBook,
        BookRegistrationPolicy $registrationPolicy,
        ExternalFailureReporter $failureReporter
    ) {
        $this->registerBook = $registerBook;
        $this->registrationPolicy = $registrationPolicy;
        $this->failureReporter = $failureReporter;
    }

    public function register($slimRequest, $response, array $args)
    {
        $request = Application::get()->getRequest();
        $handler = $request->getRouter()->getHandler();
        $submission = $handler->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $params = $slimRequest->getParsedBody();
        $thothImprintId = $params['thothImprintId'] ?? null;

        $eligibility = $this->registrationPolicy->evaluate(true, $thothImprintId, null, []);
        if ($eligibility->isImprintMissing()) {
            return $response->withStatus(400)->withJson(
                ['thothImprintId' => [__('plugins.generic.thoth.imprint.required')]]
            );
        }
        if (!$submission) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }
        if (!$request->getContext()) {
            return $response->withStatus(403)->withJsonError('api.submissions.403.contextRequired');
        }
        $eligibility = $this->registrationPolicy->evaluate(
            true,
            $thothImprintId,
            $submission->getData('thothWorkId'),
            []
        );
        if ($eligibility->isAlreadyRegistered()) {
            return $response->withStatus(403)->withJsonError('plugins.generic.thoth.api.403.alreadyRegistered');
        }

        $publication = $submission->getCurrentPublication();
        $submissionId = $submission->getId();
        $failure = ['id' => $submissionId, 'errors' => []];
        try {
            $failure['errors'] = ThothService::book()->validate($publication);
        } catch (Exception $exception) {
            $failure['errors'][] = __('plugins.generic.thoth.connectionError');
        }
        $eligibility = $this->registrationPolicy->evaluate(true, $thothImprintId, null, $failure['errors']);
        if (!$eligibility->isEligible()) {
            $failure['errors'] = $eligibility->getMetadataErrors();
            return $response->withStatus(400)->withJson($failure);
        }

        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION);
        $disableNotification = $params['disableNotification'] ?? false;
        try {
            $result = $this->registerBook->execute(
                $publication,
                new ImprintId($thothImprintId),
                new SubmissionId($submissionId)
            );
            $this->handleNotification(
                $request,
                $submission,
                true,
                $disableNotification,
                null,
                $this->warningKeys($result)
            );
        } catch (ExternalServiceFailure $exception) {
            $cause = $exception->getSafeCause() ?? __('plugins.generic.thoth.connectionError');
            $this->failureReporter->report(
                $exception,
                (int) $request->getUser()->getId(),
                new SubmissionId((int) $submissionId),
                [
                    'contextId' => (int) $request->getContext()->getId(),
                    'submissionId' => (int) $submissionId,
                    'publicationId' => (int) $publication->getId(),
                ],
                !$disableNotification
            );
            $failure['errors'][] = __(
                'plugins.generic.thoth.register.error.log',
                ['reason' => $cause]
            );
            return $response->withStatus(403)->withJson($failure);
        }

        $submission = Services::get('submission')->get($submissionId);
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $submissionProps = Services::get('submission')->getFullProperties($submission, [
            'request' => $request,
            'slimRequest' => $slimRequest,
            'userGroups' => $userGroupDao->getByContextId($submission->getData('contextId'))->toArray(),
        ]);

        return $response->withJson($submissionProps, 200);
    }

    private function warningKeys(object $result): array
    {
        return array_map(
            static function ($warning): string {
                return $warning->getMessageKey();
            },
            $result->getSynchronizationResult()->getWarnings()
        );
    }

    private function handleNotification(
        $request,
        $submission,
        $success,
        $disableNotification,
        $errorMessage = null,
        $warnings = []
    ): void {
        $notification = new ThothNotification();
        if ($disableNotification) {
            $notification->logInfo(
                $request,
                $submission,
                $success ? 'plugins.generic.thoth.register.success.log' : 'plugins.generic.thoth.register.error.log',
                $errorMessage
            );
            return;
        }

        $success
            ? $notification->notifySuccess($request, $submission)
            : $notification->notifyError($request, $submission, $errorMessage);
        foreach ($warnings as $warning) {
            $notification->notifyWarning($request, $submission, $warning);
        }
    }
}

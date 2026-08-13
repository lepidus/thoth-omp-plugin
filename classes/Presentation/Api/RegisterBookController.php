<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Api;

use APP\core\Application;
use APP\facades\Repo;
use APP\i18n\AppLocale;
use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Domain\Identifier\ImprintId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use PKP\db\DAORegistry;
use ThothApi\Exception\QueryException;

import('plugins.generic.thoth.classes.facades.ThothService');
import('plugins.generic.thoth.classes.notification.ThothNotification');

final class RegisterBookController
{
    private RegisterBook $registerBook;

    public function __construct(RegisterBook $registerBook)
    {
        $this->registerBook = $registerBook;
    }

    public function register($slimRequest, $response, array $args)
    {
        $request = Application::get()->getRequest();
        $submissionId = (int) $args['submissionId'];
        $submission = Repo::submission()->get($submissionId);
        $params = $slimRequest->getParsedBody();
        $thothImprintId = $params['thothImprintId'];

        if (!$thothImprintId) {
            return $response->withStatus(400)->withJson(
                ['thothImprintId' => [__('plugins.generic.thoth.imprint.required')]]
            );
        }
        if (!$submission) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $context = $request->getContext();
        if (!$context) {
            return $response->withStatus(403)->withJsonError('api.submissions.403.contextRequired');
        }
        if ((int) $submission->getData('contextId') !== (int) $context->getId()) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }
        if ($submission->getData('thothWorkId')) {
            return $response->withStatus(403)->withJsonError('plugins.generic.thoth.api.403.alreadyRegistered');
        }

        $publication = $submission->getCurrentPublication();
        $failure = ['id' => $submissionId, 'errors' => []];
        try {
            $failure['errors'] = \ThothService::book()->validate($publication);
        } catch (\Exception $exception) {
            $failure['errors'][] = __('plugins.generic.thoth.connectionError');
        }
        if ($failure['errors']) {
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
        } catch (QueryException $exception) {
            $this->handleNotification($request, $submission, false, $disableNotification, $exception);
            $failure['errors'][] = __(
                'plugins.generic.thoth.register.error.log',
                ['reason' => $exception->getMessage()]
            );
            return $response->withStatus(403)->withJson($failure);
        }

        $submission = Repo::submission()->get($submissionId);
        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$submission->getData('contextId')])
            ->getMany();
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        return $response->withJson(
            Repo::submission()->getSchemaMap()->mapToSubmissionsList($submission, $userGroups, $genres),
            200
        );
    }

    private function warningKeys(object $result): array
    {
        return array_map(
            static fn ($warning): string => $warning->getMessageKey(),
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
        $notification = new \ThothNotification();
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

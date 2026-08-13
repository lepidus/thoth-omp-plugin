<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Api;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Domain\Identifier\ImprintId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use APP\plugins\generic\thoth\classes\facades\ThothRepository;
use APP\plugins\generic\thoth\classes\facades\ThothService;
use APP\plugins\generic\thoth\classes\notification\ThothNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\Response;
use PKP\core\PKPBaseController;
use PKP\db\DAORegistry;
use PKP\userGroup\UserGroup;
use ThothApi\Exception\QueryException;

final class RegisterBookController
{
    public function __construct(private readonly RegisterBook $registerBook)
    {
    }

    public function register(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $request = Application::get()->getRequest();
        $submissionId = (int) $illuminateRequest->route('submissionId');
        $submission = Repo::submission()->get($submissionId);

        $thothImprintId = $illuminateRequest->input('thothImprintId');
        if (!$thothImprintId) {
            return response()->json(
                ['thothImprintId' => [__('plugins.generic.thoth.imprint.required')]],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$submission) {
            return response()->json(['error' => __('api.404.resourceNotFound')], Response::HTTP_NOT_FOUND);
        }

        if (!$request->getContext()) {
            return response()->json(
                ['error' => __('api.submissions.403.contextRequired')],
                Response::HTTP_FORBIDDEN
            );
        }

        if ($submission->getData('thothWorkId')) {
            return response()->json(
                ['error' => __('plugins.generic.thoth.api.403.alreadyRegistered')],
                Response::HTTP_FORBIDDEN
            );
        }

        $publication = $submission->getCurrentPublication();
        $failure = ['id' => $submission->getId(), 'errors' => []];
        try {
            $failure['errors'] = ThothService::book()->validate($publication);
        } catch (\Exception $exception) {
            $failure['errors'][] = __('plugins.generic.thoth.connectionError');
        }

        if ($failure['errors']) {
            return response()->json($failure, Response::HTTP_BAD_REQUEST);
        }

        $disableNotification = $illuminateRequest->input('disableNotification', false);
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
            return response()->json($failure, Response::HTTP_BAD_REQUEST);
        }

        $thothWork = ThothRepository::work()->get($result->getWorkId()->toString());
        $submission = Repo::submission()->get($submissionId);
        $userGroups = UserGroup::withContextIds($submission->getData('contextId'))->get();
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();
        $routeController = PKPBaseController::getRouteController();
        $userRoles = (array) $routeController->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $submissionProps = Repo::submission()->getSchemaMap()->map(
            $submission,
            $userGroups,
            $genres,
            $userRoles
        );
        $submissionProps['thothWorkStatus'] = $thothWork->getWorkStatus();

        return response()->json($submissionProps, Response::HTTP_OK);
    }

    private function warningKeys(object $result): array
    {
        return array_map(
            fn ($warning): string => $warning->getMessageKey(),
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

<?php


final class PublicationPublishListener
{
    private RegisterBook $registerBook;
    private object $request;
    private NotificationPublisher $notifications;
    private BookRegistrationPolicy $registrationPolicy;
    private RegistrationMetadataValidator $metadataValidator;
    private ExternalFailureReporter $failureReporter;
    public function __construct(
        RegisterBook $registerBook,
        object $request,
        NotificationPublisher $notifications,
        BookRegistrationPolicy $registrationPolicy,
        RegistrationMetadataValidator $metadataValidator,
        ExternalFailureReporter $failureReporter
    ) {
        $this->registerBook = $registerBook;
        $this->request = $request;
        $this->notifications = $notifications;
        $this->registrationPolicy = $registrationPolicy;
        $this->metadataValidator = $metadataValidator;
        $this->failureReporter = $failureReporter;
    }

    public function validate(string $hookName, array $args): void
    {
        $errors = &$args[0];
        $publication = $args[1];
        $confirmation = $this->request->getUserVar('registerConfirmation');
        $imprintId = $this->request->getUserVar('thothImprintId');
        $eligibility = $this->registrationPolicy->evaluate($confirmation, $imprintId, null, []);
        if (!$eligibility->isRequested()) {
            return;
        }
        if ($eligibility->isImprintMissing()) {
            $errors['thothImprintId'] = [__('plugins.generic.thoth.imprint.required')];
            return;
        }

        try {
            $metadataErrors = $this->metadataValidator->validate($publication);
        } catch (ExternalServiceFailure $failure) {
            $metadataErrors = [__('plugins.generic.thoth.connectionError')];
        }
        $eligibility = $this->registrationPolicy->evaluate($confirmation, $imprintId, null, $metadataErrors);
        if (!$eligibility->isEligible()) {
            $errors['thothMetadata'] = $eligibility->getMetadataErrors();
        }
    }

    public function registerThothBook(string $hookName, array $args): bool
    {
        $publication = $args[0];
        $submission = $args[2];
        $imprintId = $this->request->getUserVar('thothImprintId');
        $eligibility = $this->registrationPolicy->evaluate(
            $this->request->getUserVar('registerConfirmation'),
            $imprintId,
            $submission->getData('thothWorkId'),
            []
        );
        if (!$eligibility->isEligible()) {
            return false;
        }

        $submissionId = new SubmissionId((int) $submission->getId());
        $user = $this->request->getUser();
        $userId = $user ? (int) $user->getId() : 0;
        try {
            $result = $this->registerBook->execute(
                $publication,
                new ImprintId((string) $imprintId),
                $submissionId
            );
            $this->notifications->publishSuccess(
                $userId,
                $submissionId,
                'plugins.generic.thoth.register.success'
            );
            foreach ($result->getSynchronizationResult()->getWarnings() as $warning) {
                $this->notifications->publishWarning($userId, $submissionId, $warning->getMessageKey());
            }
        } catch (ExternalServiceFailure $failure) {
            $this->failureReporter->report($failure, $userId, $submissionId, [
                'contextId' => (int) $submission->getData('contextId'),
                'submissionId' => $submissionId->toInt(),
                'publicationId' => (int) $publication->getId(),
            ]);
        }

        return false;
    }
}

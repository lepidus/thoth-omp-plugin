<?php

import('plugins.generic.thoth.classes.Contracts.BookRegistrar');
import('plugins.generic.thoth.classes.Domain.Identifier.ImprintId');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Domain.Result.RegistrationResult');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationResult');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationWarning');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.ThothErrorTranslator');
import('plugins.generic.thoth.classes.services.ThothBookRegistrationResult');

use ThothApi\Exception\QueryException;

final class LegacyBookRegistrar implements BookRegistrar
{
    private object $service;
    private ThothErrorTranslator $errorTranslator;

    public function __construct(object $service, ?ThothErrorTranslator $errorTranslator = null)
    {
        $this->service = $service;
        $this->errorTranslator = $errorTranslator ?? new ThothErrorTranslator();
    }

    public function register(object $publication, ImprintId $imprintId): RegistrationResult
    {
        try {
            $legacyResult = $this->service->register($publication, $imprintId->toString());
        } catch (QueryException $exception) {
            throw $this->errorTranslator->registrationFailure($exception);
        }
        $synchronizationResult = new SynchronizationResult();
        if ($warning = $legacyResult->getWarning()) {
            $synchronizationResult = $synchronizationResult->withWarning(new SynchronizationWarning($warning));
        }

        return new RegistrationResult(
            new WorkId($legacyResult->getWorkId()),
            $synchronizationResult
        );
    }

    public function rollback(object $publication): void
    {
        $workId = $publication->getData('thothBookId');
        if (!$workId) {
            return;
        }

        $this->service->deleteRegisteredEntry(new ThothBookRegistrationResult($workId));
        $publication->setData('thothBookId', null);
    }
}

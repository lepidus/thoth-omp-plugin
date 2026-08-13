<?php

use ThothApi\Exception\QueryException;

import('plugins.generic.thoth.classes.Application.Exception.ExternalServiceFailure');
import('plugins.generic.thoth.classes.Application.Exception.InvalidRemoteMetadata');
import('plugins.generic.thoth.classes.Application.Exception.RegistrationFailed');
import('plugins.generic.thoth.classes.Application.Exception.RemoteWorkConflict');
import('plugins.generic.thoth.classes.Application.Exception.ThothUnavailable');

final class ThothErrorTranslator
{
    public function registrationFailure(QueryException $exception): ExternalServiceFailure
    {
        $details = $exception->getDetails();
        $safeCause = $this->safeText($this->firstMessage($exception));
        $context = $this->technicalContext($exception, $details, $safeCause);
        $code = strtoupper((string) ($details['extensions']['code'] ?? ''));
        $status = $exception->getStatusCode();
        if ($status !== null && $status >= 500) {
            return new ThothUnavailable('registration', $safeCause, $context, $exception);
        }
        if (in_array($code, ['BAD_USER_INPUT', 'INVALID_METADATA', 'VALIDATION_ERROR'], true)) {
            return new InvalidRemoteMetadata('registration', $safeCause, $context, $exception);
        }
        if ($status === 409 || in_array($code, ['CONFLICT', 'WORK_CONFLICT'], true)) {
            return new RemoteWorkConflict('registration', $safeCause, $context, $exception);
        }
        return new RegistrationFailed($safeCause, $context, $exception);
    }

    private function firstMessage(QueryException $exception): ?string
    {
        foreach ($exception->getErrors() as $error) {
            if (isset($error['message']) && is_string($error['message'])) {
                return $error['message'];
            }
        }
        return null;
    }

    private function technicalContext(QueryException $exception, array $details, ?string $safeCause): array
    {
        $context = [];
        $query = $exception->getQuery();
        if ($query && preg_match('/\b(query|mutation)\b/', $query, $matches)) {
            $context['requestType'] = $matches[1];
        }
        if ($query && preg_match('/\b(?:query|mutation)\s+([A-Za-z_][A-Za-z0-9_]*)/', $query, $matches)) {
            $context['operationName'] = $matches[1];
        }
        if ($exception->getStatusCode() !== null) {
            $context['httpStatus'] = $exception->getStatusCode();
        }
        if (isset($details['extensions']['code']) && is_scalar($details['extensions']['code'])) {
            $context['graphqlCode'] = (string) $details['extensions']['code'];
        }
        if (isset($details['path']) && is_array($details['path'])) {
            $context['graphqlPath'] = implode('.', array_map('strval', $details['path']));
        }
        $correlationId = $details['extensions']['correlationId'] ?? $details['extensions']['requestId'] ?? null;
        if (is_string($correlationId) && preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $correlationId)) {
            $context['correlationId'] = $correlationId;
        }
        $context['exceptionClass'] = get_class($exception);
        $context['exceptionMessage'] = $safeCause ?? '[redacted]';
        return $context;
    }

    private function safeText(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }
        $message = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message));
        $message = preg_replace('/\s+/', ' ', $message);
        if ($message === '' || strlen($message) > 500
            || preg_match('/(?:authorization|bearer|password|secret|token|signed.?url)/i', $message)
            || preg_match('/https?:\/\//i', $message) || preg_match('/^[\[{]/', $message)
            || preg_match('/[<>]/', $message)
        ) {
            return null;
        }
        return $message;
    }
}

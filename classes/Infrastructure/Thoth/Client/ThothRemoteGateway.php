<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client;

use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\FailureReporting\ThothErrorTranslator;
use ThothApi\Exception\QueryException;

final class ThothRemoteGateway
{
    private object $client;
    private ThothErrorTranslator $errorTranslator;

    public function __construct(object $client, ThothErrorTranslator $errorTranslator)
    {
        $this->client = $client;
        $this->errorTranslator = $errorTranslator;
    }

    public function call(string $businessOperation, string $graphqlOperation, array $arguments = [])
    {
        try {
            return $this->client->{$graphqlOperation}(...$arguments);
        } catch (QueryException $exception) {
            throw $this->errorTranslator->failure($businessOperation, $exception);
        }
    }
}

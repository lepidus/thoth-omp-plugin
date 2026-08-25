<?php


final class FrontcoverSynchronizer implements DomainSynchronizer
{
    private FrontcoverGateway $gateway;

    public function __construct(FrontcoverGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $warning = $this->gateway->synchronize($desiredState, $workId);

        return $warning ? new SynchronizationResult($warning) : new SynchronizationResult();
    }
}

<?php

interface DomainSynchronizer
{
    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult;
}

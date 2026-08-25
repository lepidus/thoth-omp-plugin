<?php


interface FrontcoverGateway
{
    public function synchronize(object $desiredState, WorkId $workId): ?SynchronizationWarning;
}

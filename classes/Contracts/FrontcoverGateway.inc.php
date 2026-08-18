<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationWarning');

interface FrontcoverGateway
{
    public function synchronize(object $desiredState, WorkId $workId): ?SynchronizationWarning;
}

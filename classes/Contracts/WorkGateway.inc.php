<?php

interface WorkGateway
{
    public function getStatus(WorkId $workId): ?string;
}

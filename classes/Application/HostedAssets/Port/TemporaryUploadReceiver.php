<?php

namespace APP\plugins\generic\thoth\classes\Application\HostedAssets\Port;

interface TemporaryUploadReceiver
{
    public function receive(string $fieldName, int $userId): ?int;
}

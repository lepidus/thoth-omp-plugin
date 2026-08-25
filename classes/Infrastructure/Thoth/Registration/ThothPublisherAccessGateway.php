<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Registration;

use APP\plugins\generic\thoth\classes\Application\Registration\Port\PublisherAccessGateway;
use APP\plugins\generic\thoth\classes\Domain\Imprint\Imprint;
use APP\plugins\generic\thoth\classes\Domain\Imprint\ImprintId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;

final class ThothPublisherAccessGateway implements PublisherAccessGateway
{
    private const ME_SELECTION = [
        'publisherContexts' => [
            'publisher' => [
                'imprints' => ['imprintId', 'imprintName'],
            ],
            'permissions' => ['cdnWrite'],
        ],
    ];

    private ?object $me = null;

    public function __construct(private readonly ThothRemoteGateway $remote)
    {
    }

    public function canUploadFiles(): bool
    {
        foreach ($this->publisherContexts() as $publisherContext) {
            if ((bool) $publisherContext->getPermissions()->getCdnWrite()) {
                return true;
            }
        }

        return false;
    }

    public function imprints(): array
    {
        $imprints = [];
        foreach ($this->publisherContexts() as $publisherContext) {
            foreach ($publisherContext->getPublisher()->getImprints() ?? [] as $imprint) {
                $id = trim((string) $imprint->getImprintId());
                if ($id === '') {
                    continue;
                }
                $imprints[$id] = new Imprint(new ImprintId($id), (string) $imprint->getImprintName());
            }
        }

        return array_values($imprints);
    }

    private function publisherContexts(): array
    {
        $this->me ??= $this->remote->call('publisherAccess', 'me', [self::ME_SELECTION]);

        return $this->me->getPublisherContexts() ?? [];
    }
}

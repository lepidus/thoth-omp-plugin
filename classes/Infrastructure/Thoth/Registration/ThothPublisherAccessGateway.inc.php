<?php


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

    private ThothRemoteGateway $remote;
    public function __construct(ThothRemoteGateway $remote)
    {
        $this->remote = $remote;
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

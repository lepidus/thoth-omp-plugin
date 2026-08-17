<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyAbstractMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyAbstractMetadataMapper;
use PKP\tests\PKPTestCase;
use stdClass;

class AbstractMetadataAdaptersTest extends PKPTestCase
{
    public const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testGatewayUsesAMinimalLongAbstractSnapshot(): void
    {
        $workRepository = new class () {
            public function get(string $workId): object
            {
                return new class () {
                    public function toArray(): array
                    {
                        return [
                            'abstracts' => [
                                [
                                    'abstractId' => 'long-id',
                                    'localeCode' => 'EN_US',
                                    'content' => 'Long abstract',
                                    'abstractType' => 'LONG',
                                    'canonical' => true,
                                    'ignored' => 'value',
                                ],
                                [
                                    'abstractId' => 'short-id',
                                    'localeCode' => 'EN_US',
                                    'content' => 'Short abstract',
                                    'abstractType' => 'SHORT',
                                    'canonical' => false,
                                ],
                            ],
                            'titles' => [['titleId' => 'title-id']],
                        ];
                    }
                };
            }
        };

        $snapshot = (new LegacyAbstractMetadataGateway($workRepository, new stdClass()))
            ->snapshot(new WorkId(self::WORK_ID));

        $this->assertSame([[
            'abstractId' => 'long-id',
            'localeCode' => 'EN_US',
            'content' => 'Long abstract',
            'abstractType' => 'LONG',
            'canonical' => true,
        ]], $snapshot);
    }

    public function testGatewayBuildsRequestLocalCreateUpdateAndDeleteMutations(): void
    {
        $repository = new RecordingAbstractRepository();
        $gateway = new LegacyAbstractMetadataGateway(new stdClass(), $repository);
        $workId = new WorkId(self::WORK_ID);
        $metadata = ['localeCode' => 'EN_US', 'content' => 'Abstract'];

        $gateway->create($workId, $metadata);
        $gateway->update($workId, 'abstract-id', $metadata);
        $gateway->delete('removed-id');

        $this->assertSame([
            ['add', ['localeCode' => 'EN_US', 'content' => 'Abstract', 'workId' => self::WORK_ID]],
            ['edit', [
                'localeCode' => 'EN_US',
                'content' => 'Abstract',
                'workId' => self::WORK_ID,
                'abstractId' => 'abstract-id',
            ]],
            ['delete', 'removed-id'],
        ], $repository->operations);
    }

    public function testMapperReturnsOnlyMutableLongAbstractsUsingThePublicationLocale(): void
    {
        $publication = new class () {
            public function getData(string $key): ?string
            {
                return $key === 'locale' ? 'pt_BR' : null;
            }
        };
        $factory = new class () {
            public array $arguments = [];

            public function createFromPublication(object $publication, string $workId, ?string $locale): array
            {
                $this->arguments = [$publication, $workId, $locale];
                return [new class () {
                    public function getAllData(): array
                    {
                        return [
                            'workId' => AbstractMetadataAdaptersTest::WORK_ID,
                            'localeCode' => 'PT_BR',
                            'content' => 'Resumo',
                            'abstractType' => 'LONG',
                            'canonical' => true,
                            'ignored' => 'value',
                        ];
                    }
                }];
            }
        };

        $abstracts = (new LegacyAbstractMetadataMapper($factory))
            ->fromPublication($publication, new WorkId(self::WORK_ID));

        $this->assertSame([$publication, self::WORK_ID, 'pt_BR'], $factory->arguments);
        $this->assertSame([[
            'localeCode' => 'PT_BR',
            'content' => 'Resumo',
            'abstractType' => 'LONG',
            'canonical' => true,
        ]], $abstracts);
    }
}

class RecordingAbstractRepository
{
    public array $operations = [];
    private array $metadata = [];

    public function new(array $metadata): object
    {
        $this->metadata = $metadata;
        return new stdClass();
    }

    public function add(object $abstract): void
    {
        $this->operations[] = ['add', $this->metadata];
    }

    public function edit(object $abstract): void
    {
        $this->operations[] = ['edit', $this->metadata];
    }

    public function delete(string $abstractId): void
    {
        $this->operations[] = ['delete', $abstractId];
    }
}

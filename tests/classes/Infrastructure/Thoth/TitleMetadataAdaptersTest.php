<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyTitleMetadataGateway');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyTitleMetadataMapper');

class TitleMetadataAdaptersTest extends PKPTestCase
{
    public const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testGatewayUsesAMinimalTitleSnapshot(): void
    {
        $workRepository = new class () {
            public function get(string $workId): object
            {
                return new class () {
                    public function toArray(): array
                    {
                        return [
                            'workId' => TitleMetadataAdaptersTest::WORK_ID,
                            'titles' => [[
                                'titleId' => 'title-id',
                                'localeCode' => 'EN_US',
                                'fullTitle' => 'Full title',
                                'title' => 'Title',
                                'subtitle' => 'Subtitle',
                                'canonical' => true,
                                'ignored' => 'value',
                            ]],
                            'abstracts' => [['abstractId' => 'abstract-id']],
                        ];
                    }
                };
            }
        };

        $snapshot = (new LegacyTitleMetadataGateway($workRepository, new stdClass()))
            ->snapshot(new WorkId(self::WORK_ID));

        $this->assertSame([[
            'titleId' => 'title-id',
            'localeCode' => 'EN_US',
            'fullTitle' => 'Full title',
            'title' => 'Title',
            'subtitle' => 'Subtitle',
            'canonical' => true,
        ]], $snapshot);
    }

    public function testGatewayBuildsRequestLocalCreateUpdateAndDeleteMutations(): void
    {
        $titleRepository = new RecordingTitleRepository();
        $gateway = new LegacyTitleMetadataGateway(new stdClass(), $titleRepository);
        $workId = new WorkId(self::WORK_ID);
        $metadata = ['localeCode' => 'EN_US', 'title' => 'Title'];

        $gateway->create($workId, $metadata);
        $gateway->update($workId, 'title-id', $metadata);
        $gateway->delete('removed-id');

        $this->assertSame([
            ['add', ['localeCode' => 'EN_US', 'title' => 'Title', 'workId' => self::WORK_ID]],
            ['edit', [
                'localeCode' => 'EN_US',
                'title' => 'Title',
                'workId' => self::WORK_ID,
                'titleId' => 'title-id',
            ]],
            ['delete', 'removed-id'],
        ], $titleRepository->operations);
    }

    public function testMapperReturnsOnlyMutableTitlesUsingThePublicationLocale(): void
    {
        $publication = new class () {
            public function getData(string $key): ?string
            {
                return $key === 'locale' ? 'pt_BR' : null;
            }
        };
        $factory = new class () {
            public $arguments = [];

            public function createFromPublication(object $publication, string $workId, ?string $locale): array
            {
                $this->arguments = [$publication, $workId, $locale];
                return [new class () {
                    public function getAllData(): array
                    {
                        return [
                            'workId' => TitleMetadataAdaptersTest::WORK_ID,
                            'localeCode' => 'PT_BR',
                            'fullTitle' => 'Titulo',
                            'title' => 'Titulo',
                            'subtitle' => null,
                            'canonical' => true,
                            'ignored' => 'value',
                        ];
                    }
                }];
            }
        };

        $titles = (new LegacyTitleMetadataMapper($factory))
            ->fromPublication($publication, new WorkId(self::WORK_ID));

        $this->assertSame([$publication, self::WORK_ID, 'pt_BR'], $factory->arguments);
        $this->assertSame([[
            'localeCode' => 'PT_BR',
            'fullTitle' => 'Titulo',
            'title' => 'Titulo',
            'subtitle' => null,
            'canonical' => true,
        ]], $titles);
    }
}

class RecordingTitleRepository
{
    public $operations = [];
    private $metadata = [];

    public function new(array $metadata): object
    {
        $this->metadata = $metadata;
        return new stdClass();
    }

    public function add(object $title): void
    {
        $this->operations[] = ['add', $this->metadata];
    }

    public function edit(object $title): void
    {
        $this->operations[] = ['edit', $this->metadata];
    }

    public function delete(string $titleId): void
    {
        $this->operations[] = ['delete', $titleId];
    }
}

<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use ThothApi\GraphQL\Enums\WorkStatus;
use ThothApi\GraphQL\Inputs\NewWork;
use ThothApi\GraphQL\Schemas\Work;

final class ThothBookRegistrarTest extends TestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';
    private const IMPRINT_ID = 'f740cf4e-16d1-487c-9a92-615882a591e9';

    public function testItCreatesAForthcomingWorkAndSynchronizesAllRegisteredMetadata(): void
    {
        $client = new RecordingBookRegistrationClient();
        $client->responses['createWork'] = new Work(['workId' => self::WORK_ID]);
        $metadata = new RecordingRegistrationSynchronizer('warning.key');
        $publication = new \stdClass();
        $registrar = new ThothBookRegistrar(
            $this->remote($client),
            new FixedRegistrationWorkMapper(),
            new SynchronizeMetadata($metadata)
        );

        $result = $registrar->register($publication, new ImprintId(self::IMPRINT_ID));

        $input = $client->inputFor('createWork');
        $this->assertInstanceOf(NewWork::class, $input);
        $this->assertSame(self::IMPRINT_ID, $input->getImprintId());
        $this->assertSame(WorkStatus::FORTHCOMING, $input->getWorkStatus());
        $this->assertSame(self::WORK_ID, $result->getWorkId()->toString());
        $this->assertSame('warning.key', $result->getSynchronizationResult()->getWarnings()[0]->getMessageKey());
        $this->assertSame($publication, $metadata->publication);
        $this->assertNotNull($metadata->workId);
        $this->assertSame(self::WORK_ID, $metadata->workId->toString());
    }

    public function testRollbackDeletesEachCreatedWorkAtMostOnce(): void
    {
        $client = new RecordingBookRegistrationClient();
        $client->responses['createWork'] = new Work(['workId' => self::WORK_ID]);
        $publication = new \stdClass();
        $registrar = new ThothBookRegistrar(
            $this->remote($client),
            new FixedRegistrationWorkMapper(),
            new SynchronizeMetadata()
        );
        $registrar->register($publication, new ImprintId(self::IMPRINT_ID));

        $registrar->rollback($publication);
        $registrar->rollback($publication);

        $this->assertSame(1, $client->count('deleteWork'));
        $this->assertSame([self::WORK_ID], $client->argumentsFor('deleteWork'));
    }

    public function testCompletedRegistrationCannotBeRolledBackLater(): void
    {
        $client = new RecordingBookRegistrationClient();
        $client->responses['createWork'] = new Work(['workId' => self::WORK_ID]);
        $publication = new \stdClass();
        $registrar = new ThothBookRegistrar(
            $this->remote($client),
            new FixedRegistrationWorkMapper(),
            new SynchronizeMetadata()
        );
        $registrar->register($publication, new ImprintId(self::IMPRINT_ID));

        $registrar->complete($publication);
        $registrar->rollback($publication);

        $this->assertSame(0, $client->count('deleteWork'));
    }

    private function remote(object $client): ThothRemoteGateway
    {
        return new ThothRemoteGateway($client, new ThothErrorTranslator());
    }
}

final class FixedRegistrationWorkMapper implements WorkMetadataMapper
{
    public function fromPublication(object $publication): array
    {
        return [
            'workType' => 'MONOGRAPH',
            'workStatus' => WorkStatus::ACTIVE,
            'landingPage' => 'https://example.test/catalog/book/example',
        ];
    }
}

final class RecordingRegistrationSynchronizer implements DomainSynchronizer
{
    public ?object $publication = null;
    public ?WorkId $workId = null;

    private string $warning;

    public function __construct(string $warning)
    {
        $this->warning = $warning;
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $this->publication = $desiredState;
        $this->workId = $workId;

        return new SynchronizationResult(new SynchronizationWarning($this->warning));
    }
}

final class RecordingBookRegistrationClient
{
    public array $operations = [];
    public array $responses = [];

    public function __call(string $operation, array $arguments)
    {
        $this->operations[] = ['operation' => $operation, 'arguments' => $arguments];

        return $this->responses[$operation] ?? null;
    }

    public function inputFor(string $operation): object
    {
        foreach ($this->argumentsFor($operation) as $argument) {
            if (is_object($argument) && method_exists($argument, 'getAllData')) {
                return $argument;
            }
        }

        throw new \RuntimeException("No input recorded for {$operation}");
    }

    public function argumentsFor(string $operation): array
    {
        foreach ($this->operations as $recorded) {
            if ($recorded['operation'] === $operation) {
                return $recorded['arguments'];
            }
        }

        throw new \RuntimeException("No operation recorded for {$operation}");
    }

    public function count(string $operation): int
    {
        return count(array_filter(
            $this->operations,
            fn (array $recorded): bool => $recorded['operation'] === $operation
        ));
    }
}

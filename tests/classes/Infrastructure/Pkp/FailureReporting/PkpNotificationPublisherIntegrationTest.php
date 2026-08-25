<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\FailureReporting;

use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\FailureReporting\PkpNotificationPublisher;
use Illuminate\Support\Facades\DB;
use PKP\notification\Notification;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\tests\PKPTestCase;

final class PkpNotificationPublisherIntegrationTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Hook::add('Schema::get::eventLog', static function (string $hookName, array $args): bool {
            $schema = $args[0];
            $schema->properties->reason = (object) [
                'type' => 'string',
                'validation' => ['nullable'],
            ];

            return false;
        });
        app('schema')->get(PKPSchemaService::SCHEMA_EVENT_LOG, true);
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        try {
            DB::rollBack();
        } finally {
            parent::tearDown();
        }
    }

    public function testItPersistsNotificationAndEventLogThroughTheRealOmpBoundaries(): void
    {
        $userId = (int) DB::table('users')->value('user_id');
        $submissionId = (int) DB::table('submissions')->value('submission_id');
        $user = Repo::user()->get($userId);
        $this->assertNotNull($user, 'The OMP dataset must contain a user');
        $this->assertNotNull(
            Repo::submission()->get($submissionId),
            'The OMP dataset must contain a submission'
        );
        $notificationsBefore = DB::table('notifications')->where('user_id', $userId)->count();
        $eventsBefore = DB::table('event_log')
            ->where('assoc_id', $submissionId)
            ->where('message', 'plugins.generic.thoth.register.error.log')
            ->count();
        $publisher = new PkpNotificationPublisher(
            new IntegrationRequest($user),
            Repo::submission(),
            new NotificationManager(),
            Repo::eventLog()
        );

        $publisher->publishError(
            $userId,
            new SubmissionId($submissionId),
            'plugins.generic.thoth.register.error',
            'integration cause'
        );

        $this->assertSame(
            $notificationsBefore + 1,
            DB::table('notifications')->where('user_id', $userId)->count()
        );
        $notification = DB::table('notifications')
            ->where('user_id', $userId)
            ->orderByDesc('notification_id')
            ->first();
        $this->assertNotNull($notification);
        $this->assertSame(Notification::NOTIFICATION_TYPE_ERROR, (int) $notification->type);
        $this->assertSame(
            $eventsBefore + 1,
            DB::table('event_log')
                ->where('assoc_id', $submissionId)
                ->where('message', 'plugins.generic.thoth.register.error.log')
                ->count()
        );
        $event = DB::table('event_log')
            ->where('assoc_id', $submissionId)
            ->where('message', 'plugins.generic.thoth.register.error.log')
            ->orderByDesc('log_id')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame($userId, (int) $event->user_id);
        $this->assertSame(
            'integration cause',
            DB::table('event_log_settings')
                ->where('log_id', $event->log_id)
                ->where('setting_name', 'reason')
                ->value('setting_value')
        );
    }
}

final class IntegrationRequest
{
    public function __construct(private readonly object $user)
    {
    }

    public function getUser(): object
    {
        return $this->user;
    }
}

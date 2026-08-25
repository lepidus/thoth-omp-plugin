<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.tests.PKPTestCase');
import('classes.notification.NotificationManager');

final class PkpNotificationPublisherIntegrationTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Capsule::connection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        try {
            Capsule::connection()->rollBack();
        } finally {
            parent::tearDown();
        }
    }

    public function testItPersistsNotificationAndEventLogThroughTheRealOmpBoundaries(): void
    {
        $userId = (int) Capsule::table('users')->value('user_id');
        $submissionId = (int) Capsule::table('submissions')->value('submission_id');
        $this->assertGreaterThan(0, $userId, 'The OMP dataset must contain a user');
        $this->assertGreaterThan(0, $submissionId, 'The OMP dataset must contain a submission');
        $user = DAORegistry::getDAO('UserDAO')->getById($userId);
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        $this->assertNotNull($user, 'The OMP dataset must contain the selected user');
        $this->assertNotNull($submission, 'The OMP dataset must contain the selected submission');
        $notificationsBefore = Capsule::table('notifications')->where('user_id', $userId)->count();
        $eventsBefore = Capsule::table('event_log')
            ->where('assoc_id', $submissionId)
            ->where('message', 'plugins.generic.thoth.register.error.log')
            ->count();
        $publisher = new PkpNotificationPublisher(
            new Pkp33IntegrationRequest($user),
            DAORegistry::getDAO('SubmissionDAO'),
            new NotificationManager(),
            DAORegistry::getDAO('SubmissionEventLogDAO')
        );

        $publisher->publishError(
            $userId,
            new SubmissionId($submissionId),
            'plugins.generic.thoth.register.error',
            'integration cause'
        );

        $this->assertSame(
            $notificationsBefore + 1,
            Capsule::table('notifications')->where('user_id', $userId)->count()
        );
        $notification = Capsule::table('notifications')
            ->where('user_id', $userId)
            ->orderByDesc('notification_id')
            ->first();
        $this->assertNotNull($notification);
        $this->assertSame(NOTIFICATION_TYPE_ERROR, (int) $notification->type);
        $this->assertSame(
            $eventsBefore + 1,
            Capsule::table('event_log')
                ->where('assoc_id', $submissionId)
                ->where('message', 'plugins.generic.thoth.register.error.log')
                ->count()
        );
        $event = Capsule::table('event_log')
            ->where('assoc_id', $submissionId)
            ->where('message', 'plugins.generic.thoth.register.error.log')
            ->orderByDesc('log_id')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame($userId, (int) $event->user_id);
        $this->assertSame(
            'integration cause',
            Capsule::table('event_log_settings')
                ->where('log_id', $event->log_id)
                ->where('setting_name', 'reason')
                ->value('setting_value')
        );
    }
}

final class Pkp33IntegrationRequest
{
    private object $user;

    public function __construct(object $user)
    {
        $this->user = $user;
    }

    public function getUser(): object
    {
        return $this->user;
    }
}

<?php

namespace Tests\Feature\Unit\Notification;

use App\Models\PrimeNotification;
use App\Models\User;
use App\Notifications\AdminBrowserPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class AdminBrowserPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_push_payload_contains_title_message_and_target_url(): void
    {
        $primeNotification = PrimeNotification::query()->create([
            'notification_type' => 'pm_waiting_review',
            'title' => 'Menunggu Review: Hasil PM Mesin MC-NTF-01',
            'message' => 'Hai, proses Preventive Maintenance untuk mesin MC-NTF-01 telah selesai dikerjakan. Mohon segera melakukan review agar proses ini dapat segera diselesaikan. Terima kasih atas kerja samanya!',
            'target_role' => 'admin',
            'related_table' => 'pm_executions',
            'related_id' => 321,
            'target_url' => '/pm/review/321',
        ]);

        $notification = new AdminBrowserPushNotification($primeNotification);

        $this->assertSame([WebPushChannel::class], $notification->via(new User));

        $webPushMessage = $notification->toWebPush(new User, $notification);
        $payload = $webPushMessage->toArray();

        $this->assertSame('Menunggu Review: Hasil PM Mesin MC-NTF-01', $payload['title']);
        $this->assertSame('Hai, proses Preventive Maintenance untuk mesin MC-NTF-01 telah selesai dikerjakan. Mohon segera melakukan review agar proses ini dapat segera diselesaikan. Terima kasih atas kerja samanya!', $payload['body']);
        $this->assertSame('/pm/review/321', $payload['data']['target_url']);
        $this->assertSame($primeNotification->id, $payload['data']['notification_id']);
    }
}

<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->create([
            'name' => 'Admin Push',
            'username' => 'admin.push',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->operator = User::query()->create([
            'name' => 'Operator Push',
            'username' => 'operator.push',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_and_update_push_subscription(): void
    {
        $payload = [
            'endpoint' => 'https://push.example.com/subscription-1',
            'keys' => [
                'p256dh' => 'public-key-one',
                'auth' => 'auth-token-one',
            ],
            'content_encoding' => 'aes128gcm',
        ];

        $this->actingAs($this->admin)
            ->postJson('/admin/push-subscriptions', $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'subscribed');

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $this->admin->id,
            'subscribable_type' => $this->admin->getMorphClass(),
            'endpoint' => $payload['endpoint'],
            'public_key' => 'public-key-one',
            'auth_token' => 'auth-token-one',
            'content_encoding' => 'aes128gcm',
        ]);

        $updatedPayload = [
            'endpoint' => 'https://push.example.com/subscription-1',
            'keys' => [
                'p256dh' => 'public-key-two',
                'auth' => 'auth-token-two',
            ],
            'content_encoding' => 'aesgcm',
        ];

        $this->actingAs($this->admin)
            ->postJson('/admin/push-subscriptions', $updatedPayload)
            ->assertOk()
            ->assertJsonPath('status', 'subscribed');

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => $updatedPayload['endpoint'],
            'public_key' => 'public-key-two',
            'auth_token' => 'auth-token-two',
            'content_encoding' => 'aesgcm',
        ]);
    }

    public function test_operator_cannot_access_push_subscription_endpoints(): void
    {
        $payload = [
            'endpoint' => 'https://push.example.com/operator',
            'keys' => [
                'p256dh' => 'operator-public-key',
                'auth' => 'operator-auth-token',
            ],
            'content_encoding' => 'aes128gcm',
        ];

        $this->actingAs($this->operator)
            ->postJson('/admin/push-subscriptions', $payload)
            ->assertRedirect('/403');

        $this->actingAs($this->operator)
            ->deleteJson('/admin/push-subscriptions', [
                'endpoint' => $payload['endpoint'],
            ])
            ->assertRedirect('/403');
    }

    public function test_admin_can_delete_only_selected_push_subscription_endpoint(): void
    {
        $this->admin->updatePushSubscription(
            'https://push.example.com/subscription-a',
            'public-key-a',
            'auth-token-a',
            'aes128gcm',
        );
        $this->admin->updatePushSubscription(
            'https://push.example.com/subscription-b',
            'public-key-b',
            'auth-token-b',
            'aes128gcm',
        );

        $this->actingAs($this->admin)
            ->deleteJson('/admin/push-subscriptions', [
                'endpoint' => 'https://push.example.com/subscription-a',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'unsubscribed');

        $this->assertDatabaseMissing('push_subscriptions', [
            'subscribable_id' => $this->admin->id,
            'endpoint' => 'https://push.example.com/subscription-a',
        ]);
        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $this->admin->id,
            'endpoint' => 'https://push.example.com/subscription-b',
        ]);
    }
}

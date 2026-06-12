<?php

namespace Tests\Feature\Feature\Settings;

use App\Models\GuestSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->create([
            'name' => 'Admin PRIME',
            'username' => 'admin.prime',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->operator = User::query()->create([
            'name' => 'Operator PRIME',
            'username' => 'operator.prime',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_access_user_setting_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/settings/users');

        $response->assertOk();
        $response->assertSee('Pengaturan User');
        $response->assertSee('Tambah User');
        $response->assertSee('Cari nama atau username...');
    }

    public function test_operator_and_guest_cannot_access_user_setting_page(): void
    {
        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Guest PRIME',
            'session_id' => 'guest-session',
            'login_at' => now(),
        ]);

        $this->actingAs($this->operator)
            ->get('/settings/users')
            ->assertRedirect('/403');

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ])->get('/settings/users')->assertRedirect('/403');

        $this->assertDatabaseHas('user_activity_logs', [
            'module_name' => 'authorization',
            'action' => 'access_denied',
        ]);
    }

    public function test_listing_supports_search_and_filters(): void
    {
        User::query()->create([
            'name' => 'Supervisor Admin',
            'username' => 'super.admin',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => false,
        ]);

        User::query()->create([
            'name' => 'Teknisi Operator',
            'username' => 'teknisi.opr',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/settings/users?search=super&role=admin&status=inactive');

        $response->assertOk();
        $response->assertSee('Supervisor Admin');
        $response->assertDontSee('Teknisi Operator');
    }

    public function test_listing_uses_ten_rows_per_page(): void
    {
        foreach (range(1, 11) as $index) {
            User::query()->create([
                'name' => "User {$index}",
                'username' => "user.{$index}",
                'password' => Hash::make('password'),
                'role' => 'operator',
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($this->admin)->get('/settings/users');

        $response->assertOk();
        $response->assertSee('Menampilkan 1-10 dari total 13 data');
    }

    public function test_admin_can_create_user_and_username_must_be_unique(): void
    {
        $createResponse = $this->actingAs($this->admin)->post('/settings/users', [
            'name' => 'Budi Santoso',
            'username' => 'budi.ops',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'operator',
            'is_active' => '1',
        ]);

        $createResponse->assertRedirect('/settings/users');
        $createResponse->assertSessionHas('flash_success', 'User berhasil ditambahkan.');

        $this->assertDatabaseHas('users', [
            'name' => 'Budi Santoso',
            'username' => 'budi.ops',
            'role' => 'operator',
            'is_active' => true,
        ]);

        $duplicateResponse = $this->actingAs($this->admin)
            ->from('/settings/users')
            ->post('/settings/users', [
                'name' => 'Budi Duplicate',
                'username' => 'budi.ops',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'admin',
                'is_active' => '1',
            ]);

        $duplicateResponse->assertRedirect('/settings/users');
        $duplicateResponse->assertSessionHasErrors('username');

        $this->assertDatabaseHas('user_activity_logs', [
            'module_name' => 'pengaturan_user',
            'action' => 'create',
        ]);
    }

    public function test_admin_can_edit_user_and_reset_password(): void
    {
        $user = User::query()->create([
            'name' => 'Dimas',
            'username' => 'dimas',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'is_active' => true,
        ]);

        $updateResponse = $this->actingAs($this->admin)->put("/settings/users/{$user->id}", [
            'name' => 'Dimas Updated',
            'username' => 'dimas.updated',
            'role' => 'admin',
            'is_active' => '0',
        ]);

        $updateResponse->assertRedirect('/settings/users');
        $updateResponse->assertSessionHas('flash_success', 'Data user berhasil diperbarui.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Dimas Updated',
            'username' => 'dimas.updated',
            'role' => 'admin',
            'is_active' => false,
        ]);

        $resetResponse = $this->actingAs($this->admin)->patch("/settings/users/{$user->id}/reset-password", [
            'new_password' => 'password.baru',
            'new_password_confirmation' => 'password.baru',
        ]);

        $resetResponse->assertRedirect('/settings/users');
        $resetResponse->assertSessionHas('flash_success', 'Password user berhasil diperbarui.');

        $this->assertTrue(Hash::check('password.baru', (string) $user->fresh()->password));

        $this->assertDatabaseHas('user_activity_logs', [
            'module_name' => 'pengaturan_user',
            'action' => 'update',
            'record_id' => $user->id,
        ]);

        $this->assertDatabaseHas('user_activity_logs', [
            'module_name' => 'pengaturan_user',
            'action' => 'reset_password',
            'record_id' => $user->id,
        ]);
    }

    public function test_admin_can_deactivate_activate_and_delete_user(): void
    {
        $managedUser = User::query()->create([
            'name' => 'Agus',
            'username' => 'agus',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->patch("/settings/users/{$managedUser->id}/nonaktifkan")
            ->assertRedirect('/settings/users')
            ->assertSessionHas('flash_success', 'User berhasil dinonaktifkan.');

        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'is_active' => false,
        ]);

        $this->actingAs($this->admin)->patch("/settings/users/{$managedUser->id}/aktifkan")
            ->assertRedirect('/settings/users')
            ->assertSessionHas('flash_success', 'User berhasil diaktifkan.');

        $this->actingAs($this->admin)->delete("/settings/users/{$managedUser->id}")
            ->assertRedirect('/settings/users')
            ->assertSessionHas('flash_success', 'User berhasil dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $managedUser->id]);

        $this->assertDatabaseHas('user_activity_logs', [
            'module_name' => 'pengaturan_user',
            'action' => 'deactivate',
            'record_id' => $managedUser->id,
        ]);

        $this->assertDatabaseHas('user_activity_logs', [
            'module_name' => 'pengaturan_user',
            'action' => 'activate',
            'record_id' => $managedUser->id,
        ]);

        $this->assertDatabaseHas('user_activity_logs', [
            'module_name' => 'pengaturan_user',
            'action' => 'delete',
            'record_id' => $managedUser->id,
        ]);
    }

    public function test_nonactive_user_cannot_login(): void
    {
        $inactiveUser = User::query()->create([
            'name' => 'Inactive User',
            'username' => 'inactive.user',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'username' => $inactiveUser->username,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}

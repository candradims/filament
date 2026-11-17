<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Only migrate an isolated in-memory database. PostgreSQL must already
        // be migrated; its integration tests use transactions and never reset it.
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->assertSame(':memory:', DB::connection()->getDatabaseName());
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Sign in');
    }

    public function test_admin_can_log_in_and_open_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk()->assertSee('Dashboard');
    }

    public function test_regular_user_cannot_log_in_to_admin_panel(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_authenticated_regular_user_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'wrong-password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_admin_can_log_out(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/admin/logout')
            ->assertRedirect('/admin/login');

        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_filament_command_creates_user_with_hashed_password(): void
    {
        $email = fake()->unique()->safeEmail();

        $this->artisan('make:filament-user', [
            '--name' => 'Test Administrator',
            '--email' => $email,
            '--password' => 'test password 123',
            '--panel' => 'admin',
        ])->assertSuccessful();

        $user = User::query()->where('email', $email)->firstOrFail();

        $this->assertTrue(Hash::check('test password 123', $user->password));
        $this->assertFalse($user->is_admin);
    }

    public function test_grant_admin_only_changes_permission_for_existing_user(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $this->artisan('app:grant-admin', ['email' => $user->email])->assertSuccessful();
        $this->artisan('app:grant-admin', ['email' => $user->email])->assertSuccessful();

        $this->assertTrue($user->refresh()->is_admin);
        $this->assertSame($originalPassword, $user->password);
    }

    public function test_grant_admin_does_not_create_unknown_user(): void
    {
        $email = fake()->unique()->safeEmail();

        $this->artisan('app:grant-admin', ['email' => $email])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_admin_permission_cannot_be_mass_assigned(): void
    {
        $user = User::query()->create([
            'name' => 'Regular User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'test password 123',
            'is_admin' => true,
        ]);

        $this->assertFalse($user->refresh()->is_admin);
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_revoked_admin_loses_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin')->assertOk();

        $admin->forceFill(['is_admin' => false])->save();

        $this->get('/admin')->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_sign_in_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_sign_in_page_renders(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Selamat datang')
            ->assertSee('images/logo.png');
    }

    public function test_users_can_sign_in(): void
    {
        $user = User::factory()->create();

        Livewire::test('pages::auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_legacy_bcrypt_hashes_still_work(): void
    {
        // v1 stored bcrypt hashes with cost 10; they must keep working.
        $user = User::factory()->create();
        // Written straight to the table, the way the imported rows arrived.
        DB::table('users')->where('id', $user->id)
            ->update(['password' => password_hash('secret-v1', PASSWORD_BCRYPT, ['cost' => 10])]);

        Livewire::test('pages::auth.login')
            ->set('email', $user->email)
            ->set('password', 'secret-v1')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::test('pages::auth.login')
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email')
            ->assertSee('Email atau kata sandi salah')
            ->assertSet('password', '');

        $this->assertGuest();
    }

    public function test_sign_in_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            Livewire::test('pages::auth.login')
                ->set('email', $user->email)
                ->set('password', 'wrong-password')
                ->call('login');
        }

        Livewire::test('pages::auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_signed_in_users_see_the_home_page_and_can_sign_out(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/')->assertOk()->assertSee($user->name)->assertSee('Semua toko');

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_signed_in_users_are_sent_away_from_the_sign_in_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('login'))->assertRedirect();
    }
}

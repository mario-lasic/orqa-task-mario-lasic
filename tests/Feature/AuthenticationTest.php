<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AuthenticationTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('documents.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_cannot_access_admin_documents(): void
    {
        $this->seed();
        $user = User::where('username', 'ivan')->firstOrFail();
        $this->actingAs($user)
            ->get(route('admin.documents.index'))
            ->assertForbidden();
    }

    public function test_user_can_access_documents(): void
    {
        $this->seed();
        $user = User::where('username', 'ivan')->firstOrFail();

        $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertOk();
    }

    public function test_super_admin_can_access_admin_documents(): void
    {
        $this->seed();
        $user = User::where('username', 'superadmin')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.documents.index'))
            ->assertOk();
    }

    public function test_super_admin_without_user_role_cannot_access_documents(): void
    {
        $this->seed();
        $user = User::where('username', 'superadmin')->firstOrFail();

        $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertForbidden();
    }

    public function test_user_with_both_roles_can_access_documents(): void
    {
        $this->seed();
        $user = User::where('username', 'marko')->firstOrFail();

        $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertOk();
    }

    public function test_user_with_both_roles_can_access_admin_documents(): void
    {
        $this->seed();
        $user = User::where('username', 'marko')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.documents.index'))
            ->assertOk();
    }

    public function test_guest_is_redirected_to_login_from_admin_documents(): void
    {
        $this->get(route('admin.documents.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_log_in_with_username_and_password(): void
    {
        $this->seed();
        $user = User::where('username', 'ivan')->firstOrFail();

        $this->post(route('login'), [
            'username' => 'ivan',
            'password' => 'password',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_log_in_with_wrong_password(): void
    {
        $this->seed();

        $this->from(route('login'))
            ->post(route('login'), [
                'username' => 'ivan',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $this->seed();
        $user = User::where('username', 'ivan')->firstOrFail();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_user_home_redirects_to_documents(): void
    {
        $this->seed();
        $user = User::where('username', 'ivan')->firstOrFail();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('documents.index'));
    }

    public function test_super_admin_home_redirects_to_admin_documents(): void
    {
        $this->seed();
        $user = User::where('username', 'superadmin')->firstOrFail();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('admin.documents.index'));
    }

    public function test_user_with_both_roles_home_redirects_to_admin_documents(): void
    {
        $this->seed();
        $user = User::where('username', 'marko')->firstOrFail();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('admin.documents.index'));
    }

    public function test_login_attempts_are_limited(): void
    {
        $this->seed();

        $credentials = [
            'username' => 'ivan',
            'password' => 'wrong-password',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login'), $credentials)
                ->assertSessionHasErrors('username');
        }

        $this->post(route('login'), $credentials)
            ->assertStatus(429);

        $this->assertGuest();
    }

}

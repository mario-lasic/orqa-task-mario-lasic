<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminUserListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_super_admin_sees_only_users_with_user_role(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertViewIs('admin.users.index')
            ->assertSeeText('Korisnici');

        $users = $response->viewData('users');

        $this->assertSame(3, $users->total());

        $this->assertSame(
            ['ana', 'ivan', 'marko'],
            $users->getCollection()->pluck('username')->all()
        );

        $this->assertNotContains(
            $admin->id,
            $users->getCollection()->modelKeys()
        );
    }

    public function test_guest_cannot_access_user_listing(): void
    {
        $this->get(route('admin.users.index'))
            ->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_access_user_listing(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_user_with_both_roles_can_access_user_listing(): void
    {
        $user = User::where('username', 'marko')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertViewHas(
                'users',
                fn ($users) => $users->getCollection()
                    ->contains('id', $user->id)
            );
    }

    #[DataProvider('storageCases')]
    public function test_listing_displays_correct_storage_for_each_user(
        int $usedBytes,
        string $expectedUsage,
        string $expectedFree
    ): void {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $ivan = User::where('username', 'ivan')->firstOrFail();
        $ana = User::where('username', 'ana')->firstOrFail();

        $ivan->storage_quota_bytes = 3_000_000_000;
        $ivan->save();

        if ($usedBytes > 0) {
            Document::factory()->for($ivan)->create([
                'size_bytes' => $usedBytes,
            ]);
        }

        // Anini dokumenti ne smiju povećati Ivanovo zauzeće.
        Document::factory()->for($ana)->create([
            'size_bytes' => 500_000_000,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSeeTextInOrder([
                'ivan',
                $expectedUsage,
                $expectedFree,
            ]);

        $listedIvan = $response->viewData('users')
            ->getCollection()
            ->firstWhere('id', $ivan->id);

        $this->assertNotNull($listedIvan);
        $this->assertSame($usedBytes, $listedIvan->usedStorageBytes());

        $this->assertSame(
            3_000_000_000 - $usedBytes,
            $listedIvan->remainingStorageBytes()
        );

        $this->assertSame(
            $ivan->usedStorageBytes(),
            $listedIvan->usedStorageBytes()
        );
    }

    public static function storageCases(): array
    {
        return [
            'empty quota' => [
                0,
                '0.0/3.0 GB',
                '(3.000.000.000 B slobodno)',
            ],
            'partially used quota' => [
                1_000_000_000,
                '1.0/3.0 GB',
                '(2.000.000.000 B slobodno)',
            ],
            'full quota' => [
                3_000_000_000,
                '3.0/3.0 GB',
                '(0 B slobodno)',
            ],
        ];
    }

    public function test_users_are_sorted_and_paginated(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $role = Role::where('name', 'user')->firstOrFail();

        for ($number = 1; $number <= 6; $number++) {
            $user = User::factory()->create([
                'username' => "a-test-{$number}",
            ]);

            $user->roles()->attach($role->id);
        }

        $firstResponse = $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk();

        $firstPage = $firstResponse->viewData('users');

        $this->assertSame(9, $firstPage->total());
        $this->assertSame(5, $firstPage->perPage());

        $this->assertSame(
            ['a-test-1', 'a-test-2', 'a-test-3', 'a-test-4', 'a-test-5'],
            $firstPage->getCollection()->pluck('username')->all()
        );

        $secondResponse = $this->get(
            route('admin.users.index', ['page' => 2])
        )->assertOk();

        $secondPage = $secondResponse->viewData('users');

        $this->assertSame(2, $secondPage->currentPage());

        $this->assertSame(
            ['a-test-6', 'ana', 'ivan', 'marko'],
            $secondPage->getCollection()->pluck('username')->all()
        );
    }

    public function test_empty_listing_displays_empty_state(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $role = Role::where('name', 'user')->firstOrFail();

        // Uklanjamo samo user ulogu; administratorska ostaje.
        $role->users()->detach();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSeeText('Nema korisnika s ulogom user.')
            ->assertViewHas(
                'users',
                fn ($users) => $users->total() === 0
            );
    }
}

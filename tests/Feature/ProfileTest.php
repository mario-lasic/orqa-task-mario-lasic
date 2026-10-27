<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_user_can_view_their_profile(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertViewIs('profile.edit')
            ->assertViewHas(
                'user',
                fn (User $profileUser) => $profileUser->id === $user->id
            )
            ->assertSeeText('Moj profil');
    }

    public function test_user_can_update_their_profile(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();
        $otherUser = User::where('username', 'ana')->firstOrFail();

        $otherBefore = $otherUser->only([
            'name',
            'last_name',
            'address',
        ]);

        $data = [
            'name' => 'Novo ime',
            'last_name' => 'Novo prezime',
            'address' => 'Nova adresa 12',
        ];

        $this->actingAs($user)
            ->patch(route('profile.update'), $data)
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas(
                'success',
                'Profil je uspješno ažuriran.'
            );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            ...$data,
        ]);

        $this->assertSame(
            $otherBefore,
            $otherUser->fresh()->only(['name', 'last_name', 'address'])
        );

        // Novi zahtjev mora prikazati spremljene podatke.
        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertViewHas(
                'user',
                fn (User $profileUser) => $profileUser->name === $data['name']
                    && $profileUser->last_name === $data['last_name']
                    && $profileUser->address === $data['address']
            );
    }

    public function test_invalid_profile_data_is_not_saved(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();

        $before = $user->only(['name', 'last_name', 'address']);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => '',
                'last_name' => str_repeat('a', 256),
                'address' => ['nije tekst'],
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors([
                'name',
                'last_name',
                'address',
            ])
            ->assertSessionMissing('success');

        $this->assertSame(
            $before,
            $user->fresh()->only(['name', 'last_name', 'address'])
        );
    }

    public function test_guest_cannot_view_or_update_profile(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();
        $before = $user->getAttributes();

        $this->get(route('profile.edit'))
            ->assertRedirect(route('login'));

        $this->patch(route('profile.update'), [
            'name' => 'Promijenjeno',
            'last_name' => 'Prezime',
            'address' => 'Adresa 1',
        ])->assertRedirect(route('login'));

        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_super_admin_without_user_role_cannot_access_profile(): void
    {
        $user = User::where('username', 'superadmin')->firstOrFail();
        $before = $user->getAttributes();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Promijenjeno',
                'last_name' => 'Prezime',
                'address' => 'Adresa 1',
            ])
            ->assertForbidden();

        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_profile_update_ignores_protected_fields_and_other_user_id(): void
    {
        $ivan = User::where('username', 'ivan')->firstOrFail();
        $ana = User::where('username', 'ana')->firstOrFail();

        $protectedFields = [
            'id',
            'username',
            'email',
            'password',
            'storage_quota_bytes',
        ];

        $protectedBefore = $ivan->only($protectedFields);
        $anaBefore = $ana->getAttributes();

        $rolesBefore = $ivan->roles()
            ->orderBy('roles.id')
            ->pluck('roles.id')
            ->all();

        $adminRoleId = Role::where('name', 'super_admin')
            ->firstOrFail()
            ->id;

        $this->actingAs($ivan)
            ->patch(route('profile.update'), [
                'name' => 'Novo ime',
                'last_name' => 'Novo prezime',
                'address' => 'Nova adresa 1',
                'id' => $ana->id,
                'user_id' => $ana->id,
                'username' => 'lazni-username',
                'email' => 'lazni@example.com',
                'password' => 'NovaLozinka123!',
                'storage_quota_bytes' => 9999999999,
                'roles' => [$adminRoleId],
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $ivan->id,
            'name' => 'Novo ime',
            'last_name' => 'Novo prezime',
            'address' => 'Nova adresa 1',
        ]);

        $this->assertSame(
            $protectedBefore,
            $ivan->fresh()->only($protectedFields)
        );

        $this->assertSame(
            $rolesBefore,
            $ivan->roles()->orderBy('roles.id')->pluck('roles.id')->all()
        );

        $this->assertSame($anaBefore, $ana->fresh()->getAttributes());
    }

    public function test_user_with_both_roles_can_view_and_update_profile(): void
    {
        $user = User::where('username', 'marko')->firstOrFail();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();

        $this->patch(route('profile.update'), [
            'name' => 'Marko',
            'last_name' => 'Novo prezime',
            'address' => 'Nova adresa 5',
        ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Marko',
            'last_name' => 'Novo prezime',
            'address' => 'Nova adresa 5',
        ]);
    }

    public function test_storage_display_handles_empty_partial_and_full_quota(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();
        $otherUser = User::where('username', 'ana')->firstOrFail();

        $user->storage_quota_bytes = 3_000_000_000;
        $user->save();

        // Tuđi dokument ne smije utjecati na prikaz.
        Document::factory()->for($otherUser)->create([
            'size_bytes' => 2_000_000_000,
        ]);

        $cases = [
            [0, '0.0/3.0 GB', '(3.000.000.000 B slobodno)'],
            [1_000_000_000, '1.0/3.0 GB', '(2.000.000.000 B slobodno)'],
            [3_000_000_000, '3.0/3.0 GB', '(0 B slobodno)'],
        ];

        foreach ($cases as [$usedBytes, $expectedUsage, $expectedFree]) {
            $user->documents()->delete();

            if ($usedBytes > 0) {
                Document::factory()->for($user)->create([
                    'size_bytes' => $usedBytes,
                ]);
            }

            foreach (['profile.edit', 'documents.index'] as $route) {
                $this->actingAs($user)
                    ->get(route($route))
                    ->assertOk()
                    ->assertSeeText('Zauzeto:')
                    ->assertSeeText($expectedUsage)
                    ->assertSeeText($expectedFree);
            }
        }
    }

    public function test_profile_storage_updates_after_upload_and_deletion(): void
    {
        Storage::fake('local');

        $user = User::where('username', 'ivan')->firstOrFail();
        $user->storage_quota_bytes = 1000;
        $user->save();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSeeText('(1.000 B slobodno)');

        $file = UploadedFile::fake()->createWithContent(
            'primjer.txt',
            'Pozdrav'
        );

        $this->post(route('documents.store'), [
            'document' => $file,
        ])
            ->assertRedirect(route('documents.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSeeText('(993 B slobodno)');

        $document = $user->documents()->firstOrFail();

        $this->from(route('documents.index'))
            ->delete(route('documents.destroy', $document))
            ->assertRedirect(route('documents.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSeeText('(1.000 B slobodno)');
    }
}

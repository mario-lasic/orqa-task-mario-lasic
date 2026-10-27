<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed();
    }

    public function test_owner_can_delete_their_document_and_free_space(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();

        $user->storage_quota_bytes = 1000;
        $user->save();

        $document = $this->createStoredDocument($user);

        $this->assertSame(7, $user->usedStorageBytes());
        $this->assertSame(993, $user->remainingStorageBytes());

        $this->actingAs($user)
            ->from(route('documents.index'))
            ->delete(route('documents.destroy', $document))
            ->assertRedirect(route('documents.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas(
                'success',
                'Dokument je uspješno obrisan.'
            );

        $this->assertDatabaseMissing('documents', [
            'id' => $document->id,
        ]);

        Storage::disk('local')->assertMissing($document->path);

        $this->assertSame(0, $user->usedStorageBytes());
        $this->assertSame(1000, $user->remainingStorageBytes());
    }

    public function test_other_user_cannot_delete_the_document(): void
    {
        $ivan = User::where('username', 'ivan')->firstOrFail();
        $ana = User::where('username', 'ana')->firstOrFail();

        $document = $this->createStoredDocument($ivan);

        $this->actingAs($ana)
            ->delete(route('documents.destroy', $document))
            ->assertForbidden();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'user_id' => $ivan->id,
        ]);

        $this->assertSame(
            'Pozdrav',
            Storage::disk('local')->get($document->path)
        );

        $this->assertSame(7, $ivan->usedStorageBytes());
    }

    public function test_guest_cannot_delete_a_document(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();
        $document = $this->createStoredDocument($user);

        $this->delete(route('documents.destroy', $document))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
        ]);

        Storage::disk('local')->assertExists($document->path);
    }

    public function test_super_admin_can_delete_another_users_document(): void
    {
        $ivan = User::where('username', 'ivan')->firstOrFail();
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $document = $this->createStoredDocument($ivan);
        $freeBefore = $ivan->remainingStorageBytes();

        $this->actingAs($admin)
            ->from(route('admin.documents.index'))
            ->delete(route('documents.destroy', $document))
            ->assertRedirect(route('admin.documents.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('documents', [
            'id' => $document->id,
        ]);

        Storage::disk('local')->assertMissing($document->path);

        $this->assertSame(
            $freeBefore + 7,
            $ivan->remainingStorageBytes()
        );
    }

    public function test_record_can_be_deleted_when_file_is_already_missing(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();

        $document = Document::factory()->for($user)->create([
            'path' => 'documents/missing.txt',
            'size_bytes' => 7,
        ]);

        $this->actingAs($user)
            ->from(route('documents.index'))
            ->delete(route('documents.destroy', $document))
            ->assertRedirect(route('documents.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('documents', [
            'id' => $document->id,
        ]);

        $this->assertSame(0, $user->usedStorageBytes());
    }

    public function test_deleting_the_same_document_twice_returns_not_found(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();
        $document = $this->createStoredDocument($user);

        $url = route('documents.destroy', $document);

        $this->actingAs($user)
            ->from(route('documents.index'))
            ->delete($url)
            ->assertRedirect(route('documents.index'));

        $this->actingAs($user)
            ->delete($url)
            ->assertNotFound();

        $this->assertDatabaseMissing('documents', [
            'id' => $document->id,
        ]);

        Storage::disk('local')->assertMissing($document->path);
    }

    private function createStoredDocument(User $user): Document
    {
        $document = Document::factory()->for($user)->create([
            'original_name' => 'primjer.txt',
            'size_bytes' => 7,
            'extension' => 'txt',
        ]);

        Storage::disk('local')->put($document->path, 'Pozdrav');

        return $document;
    }

    public function test_failed_file_deletion_restores_the_database_record(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();
        $document = $this->createStoredDocument($user);

        $disk = Storage::disk('local');
        $freeBefore = $user->remainingStorageBytes();

        $failingDisk = \Mockery::mock(
            FilesystemAdapter::class
        );

        $failingDisk->shouldReceive('delete')
            ->with($document->path)
            ->once()
            ->andReturn(false);

        Storage::shouldReceive('disk')
            ->with('local')
            ->once()
            ->andReturn($failingDisk);

        $this->actingAs($user)
            ->from(route('documents.index'))
            ->delete(route('documents.destroy', $document))
            ->assertRedirect(route('documents.index'))
            ->assertSessionHasErrors('document')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'user_id' => $user->id,
            'size_bytes' => 7,
        ]);

        $this->assertSame('Pozdrav', $disk->get($document->path));
        $this->assertSame(7, $user->usedStorageBytes());
        $this->assertSame($freeBefore, $user->remainingStorageBytes());
    }

    public function test_file_deletion_exception_restores_the_database_record(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();
        $document = $this->createStoredDocument($user);

        $disk = Storage::disk('local');
        $freeBefore = $user->remainingStorageBytes();

        $failure = new \RuntimeException('Simulirana greška brisanja.');

        $failingDisk = \Mockery::mock(
            FilesystemAdapter::class
        );

        $failingDisk->shouldReceive('delete')
            ->with($document->path)
            ->once()
            ->andThrow($failure);

        Storage::shouldReceive('disk')
            ->with('local')
            ->once()
            ->andReturn($failingDisk);

        $this->withoutExceptionHandling();

        $caughtException = null;

        try {
            $this->actingAs($user)
                ->delete(route('documents.destroy', $document));
        } catch (\Throwable $exception) {
            $caughtException = $exception;
        }

        $this->assertSame($failure, $caughtException);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'user_id' => $user->id,
            'size_bytes' => 7,
        ]);

        $this->assertSame('Pozdrav', $disk->get($document->path));
        $this->assertSame(7, $user->usedStorageBytes());
        $this->assertSame($freeBefore, $user->remainingStorageBytes());
    }
}

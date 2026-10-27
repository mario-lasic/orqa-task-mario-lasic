<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_a_document(): void
    {
        Storage::fake('local');
        $this->seed();

        $user = User::where('username', 'ivan')->firstOrFail();

        $file = UploadedFile::fake()->createWithContent(
            'primjer.txt',
            'Pozdrav'
        );

        $this->actingAs($user)
            ->post(route('documents.store'), [
                'document' => $file,
            ])
            ->assertRedirect(route('documents.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas(
                'success',
                'Datoteka je uspješno učitana.'
            );

        $this->assertDatabaseCount('documents', 1);

        $document = $user->documents()->firstOrFail();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'user_id' => $user->id,
            'original_name' => 'primjer.txt',
            'size_bytes' => 7,
            'extension' => 'txt',
        ]);

        $this->assertStringStartsWith(
            "documents/{$user->id}/",
            $document->path
        );

        Storage::disk('local')->assertExists($document->path);

        $this->assertSame(
            'Pozdrav',
            Storage::disk('local')->get($document->path)
        );
    }

    public function test_upload_can_exactly_fill_remaining_quota(): void
    {
        Storage::fake('local');
        $this->seed();

        $user = User::where('username', 'ivan')->firstOrFail();
        $user->storage_quota_bytes = 1000;
        $user->save();

        Document::factory()->for($user)->create([
            'size_bytes' => 400,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'tocno.txt',
            str_repeat('a', 600)
        );

        $this->actingAs($user)
            ->post(route('documents.store'), [
                'document' => $file,
            ])
            ->assertRedirect(route('documents.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('documents', 2);

        $document = $user->documents()
            ->where('original_name', 'tocno.txt')
            ->firstOrFail();

        $this->assertSame(600, $document->size_bytes);
        $this->assertSame(1000, $user->usedStorageBytes());
        $this->assertSame(0, $user->remainingStorageBytes());

        Storage::disk('local')->assertExists($document->path);

        $this->assertSame(
            str_repeat('a', 600),
            Storage::disk('local')->get($document->path)
        );
    }

    public function test_upload_exceeding_quota_by_one_byte_is_rejected(): void
    {
        Storage::fake('local');
        $this->seed();

        $user = User::where('username', 'ivan')->firstOrFail();
        $user->storage_quota_bytes = 1000;
        $user->save();

        $existingDocument = Document::factory()
            ->for($user)
            ->create([
                'size_bytes' => 400,
            ]);

        Storage::disk('local')->put(
            $existingDocument->path,
            str_repeat('b', 400)
        );

        $file = UploadedFile::fake()->createWithContent(
            'previse.txt',
            str_repeat('a', 601)
        );

        $this->actingAs($user)
            ->from(route('documents.index'))
            ->post(route('documents.store'), [
                'document' => $file,
            ])
            ->assertRedirect(route('documents.index'))
            ->assertSessionHasErrors('document')
            ->assertSessionMissing('success');

        $this->assertDatabaseCount('documents', 1);
        $this->assertDatabaseMissing('documents', [
            'original_name' => 'previse.txt',
        ]);

        $this->assertSame(400, $user->usedStorageBytes());
        $this->assertSame(600, $user->remainingStorageBytes());

        $this->assertSame(
            [$existingDocument->path],
            Storage::disk('local')->allFiles()
        );

        $this->assertSame(
            str_repeat('b', 400),
            Storage::disk('local')->get($existingDocument->path)
        );
    }

    public function test_upload_requires_a_file(): void
    {
        Storage::fake('local');
        $this->seed();

        $user = User::where('username', 'ivan')->firstOrFail();

        $this->actingAs($user)
            ->from(route('documents.index'))
            ->post(route('documents.store'), [])
            ->assertRedirect(route('documents.index'))
            ->assertSessionHasErrors('document')
            ->assertSessionMissing('success');

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_text_cannot_be_uploaded_as_a_file(): void
    {
        Storage::fake('local');
        $this->seed();

        $user = User::where('username', 'ivan')->firstOrFail();

        $this->actingAs($user)
            ->from(route('documents.index'))
            ->post(route('documents.store'), [
                'document' => 'Ovo nije datoteka.',
            ])
            ->assertRedirect(route('documents.index'))
            ->assertSessionHasErrors('document')
            ->assertSessionMissing('success');

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_file_larger_than_two_mib_is_rejected(): void
    {
        Storage::fake('local');
        $this->seed();

        $user = User::where('username', 'ivan')->firstOrFail();

        $file = UploadedFile::fake()->create(
            'prevelika.pdf',
            2049,
            'application/pdf'
        );

        $this->actingAs($user)
            ->from(route('documents.index'))
            ->post(route('documents.store'), [
                'document' => $file,
            ])
            ->assertRedirect(route('documents.index'))
            ->assertSessionHasErrors('document')
            ->assertSessionMissing('success');

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_guest_cannot_upload_a_document(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent(
            'primjer.txt',
            'Pozdrav'
        );

        $this->post(route('documents.store'), [
            'document' => $file,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_super_admin_without_user_role_cannot_upload(): void
    {
        Storage::fake('local');
        $this->seed();

        $user = User::where('username', 'superadmin')->firstOrFail();

        $file = UploadedFile::fake()->createWithContent(
            'primjer.txt',
            'Pozdrav'
        );

        $this->actingAs($user)
            ->post(route('documents.store'), [
                'document' => $file,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_user_with_both_roles_can_upload_a_document(): void
    {
        Storage::fake('local');
        $this->seed();

        $user = User::where('username', 'marko')->firstOrFail();

        $file = UploadedFile::fake()->createWithContent(
            'marko.txt',
            'Pozdrav'
        );

        $this->actingAs($user)
            ->post(route('documents.store'), [
                'document' => $file,
            ])
            ->assertRedirect(route('documents.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('documents', 1);

        $document = $user->documents()->firstOrFail();

        $this->assertSame($user->id, $document->user_id);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_upload_ignores_forged_metadata(): void
    {
        Storage::fake('local');
        $this->seed();

        $ivan = User::where('username', 'ivan')->firstOrFail();
        $ana = User::where('username', 'ana')->firstOrFail();
        $originalQuota = $ivan->storage_quota_bytes;

        $file = UploadedFile::fake()->createWithContent(
            'stvarni.txt',
            'Pozdrav'
        );

        $this->actingAs($ivan)
            ->post(route('documents.store'), [
                'document' => $file,
                'user_id' => $ana->id,
                'original_name' => 'lazni.pdf',
                'path' => 'lazna/putanja.pdf',
                'size_bytes' => 1,
                'extension' => 'pdf',
                'storage_quota_bytes' => 9999999999,
            ])
            ->assertRedirect(route('documents.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('documents', 1);

        $document = $ivan->documents()->firstOrFail();

        $this->assertSame($ivan->id, $document->user_id);
        $this->assertSame('stvarni.txt', $document->original_name);
        $this->assertSame(7, $document->size_bytes);
        $this->assertSame('txt', $document->extension);
        $this->assertStringStartsWith(
            "documents/{$ivan->id}/",
            $document->path
        );

        $this->assertSame(0, $ana->documents()->count());
        $this->assertSame(
            $originalQuota,
            $ivan->fresh()->storage_quota_bytes
        );

        Storage::disk('local')->assertExists($document->path);
        Storage::disk('local')->assertMissing('lazna/putanja.pdf');
    }

    public function test_other_users_documents_do_not_reduce_upload_quota(): void
    {
        Storage::fake('local');
        $this->seed();

        $ivan = User::where('username', 'ivan')->firstOrFail();
        $ana = User::where('username', 'ana')->firstOrFail();

        $ivan->storage_quota_bytes = 1000;
        $ivan->save();

        Document::factory()->for($ana)->create([
            'size_bytes' => 1000,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'ivan.txt',
            str_repeat('a', 1000)
        );

        $this->actingAs($ivan)
            ->post(route('documents.store'), [
                'document' => $file,
            ])
            ->assertRedirect(route('documents.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('documents', 2);
        $this->assertSame(1, $ivan->documents()->count());
        $this->assertSame(1000, $ivan->usedStorageBytes());
        $this->assertSame(0, $ivan->remainingStorageBytes());

        $document = $ivan->documents()->firstOrFail();

        Storage::disk('local')->assertExists($document->path);
    }

    public function test_files_with_the_same_original_name_do_not_overwrite_each_other(): void
    {
        Storage::fake('local');
        $this->seed();

        $user = User::where('username', 'ivan')->firstOrFail();

        foreach (['Prvi sadrzaj', 'Drugi sadrzaj'] as $content) {
            $file = UploadedFile::fake()->createWithContent(
                'isti-naziv.txt',
                $content
            );

            $this->actingAs($user)
                ->post(route('documents.store'), [
                    'document' => $file,
                ])
                ->assertRedirect(route('documents.index'))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('success');
        }

        $this->assertDatabaseCount('documents', 2);

        $documents = $user->documents()->orderBy('id')->get();

        $this->assertCount(2, $documents);
        $this->assertSame('isti-naziv.txt', $documents[0]->original_name);
        $this->assertSame('isti-naziv.txt', $documents[1]->original_name);
        $this->assertNotSame($documents[0]->path, $documents[1]->path);

        $this->assertCount(2, Storage::disk('local')->allFiles());

        $this->assertSame(
            'Prvi sadrzaj',
            Storage::disk('local')->get($documents[0]->path)
        );

        $this->assertSame(
            'Drugi sadrzaj',
            Storage::disk('local')->get($documents[1]->path)
        );
    }

    public function test_storage_failure_does_not_create_a_document(): void
    {
        $disk = Storage::fake('local');
        $this->seed();

        $user = User::where('username', 'ivan')->firstOrFail();

        $file = UploadedFile::fake()->createWithContent(
            'primjer.txt',
            'Pozdrav'
        );

        $failure = new \RuntimeException('Simulirana greška diska.');

        $failingDisk = \Mockery::mock(
            FilesystemAdapter::class
        );

        $failingDisk->shouldReceive('putFileAs')
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
                ->post(route('documents.store'), [
                    'document' => $file,
                ]);
        } catch (\Throwable $exception) {
            $caughtException = $exception;
        }

        $this->assertSame($failure, $caughtException);
        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], $disk->allFiles());
    }

    public function test_database_failure_removes_the_stored_file(): void
    {
        $disk = Storage::fake('local');
        $this->seed();

        $user = User::where('username', 'ivan')->firstOrFail();

        $file = UploadedFile::fake()->createWithContent(
            'primjer.txt',
            'Pozdrav'
        );

        $failure = new \RuntimeException('Simulirana greška baze.');

        $originalDispatcher = Document::getEventDispatcher();
        $testDispatcher = clone $originalDispatcher;

        Document::setEventDispatcher($testDispatcher);

        $storedPathsAtFailure = [];

        $testDispatcher->listen(
            'eloquent.creating: '.Document::class,
            function ($document) use ($failure, $disk, &$storedPathsAtFailure) {
                $storedPathsAtFailure = $disk->allFiles();

                throw $failure;
            }
        );

        $this->withoutExceptionHandling();

        $caughtException = null;

        try {
            $this->actingAs($user)
                ->post(route('documents.store'), [
                    'document' => $file,
                ]);
        } catch (\Throwable $exception) {
            $caughtException = $exception;
        } finally {
            Document::setEventDispatcher($originalDispatcher);
        }

        $this->assertSame($failure, $caughtException);

        $this->assertCount(1, $storedPathsAtFailure);

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], $disk->allFiles());
        $this->assertSame(0, $user->usedStorageBytes());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed();
    }

    public function test_owner_can_download_their_document(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();
        $document = $this->createStoredDocument($user);

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertOk()
            ->assertDownload('primjer.txt')
            ->assertStreamedContent('Pozdrav');
    }

    public function test_other_user_cannot_download_the_document(): void
    {
        $ivan = User::where('username', 'ivan')->firstOrFail();
        $ana = User::where('username', 'ana')->firstOrFail();
        $document = $this->createStoredDocument($ivan);

        $this->actingAs($ana)
            ->get(route('documents.download', $document))
            ->assertForbidden();

        Storage::disk('local')->assertExists($document->path);
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
        ]);
    }

    public function test_guest_cannot_download_a_document(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();
        $document = $this->createStoredDocument($user);

        $this->get(route('documents.download', $document))
            ->assertRedirect(route('login'));
    }

    public function test_super_admin_can_download_another_users_document(): void
    {
        $ivan = User::where('username', 'ivan')->firstOrFail();
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $document = $this->createStoredDocument($ivan);

        $this->actingAs($admin)
            ->get(route('documents.download', $document))
            ->assertOk()
            ->assertDownload('primjer.txt')
            ->assertStreamedContent('Pozdrav');
    }

    public function test_missing_file_returns_not_found(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();

        $document = Document::factory()->for($user)->create([
            'path' => 'documents/missing.txt',
        ]);

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertNotFound();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
        ]);
    }

    public function test_missing_document_returns_not_found(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();

        $this->actingAs($user)
            ->get(route('documents.download', ['document' => 999999]))
            ->assertNotFound();
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
}

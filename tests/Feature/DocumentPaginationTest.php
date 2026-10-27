<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed();
    }

    public static function listingCases(): array
    {
        return [
            'own documents' => ['ivan', 'documents.index'],
            'all documents' => ['superadmin', 'admin.documents.index'],
        ];
    }

    #[DataProvider('listingCases')]
    public function test_empty_listing_redirects_excessive_page_to_first(
        string $username,
        string $route
    ): void {
        $user = User::where('username', $username)->firstOrFail();

        $this->actingAs($user)
            ->get(route($route, ['page' => 99]))
            ->assertRedirect(route($route, ['page' => 1]));

        $this->get(route($route, ['page' => 1]))
            ->assertOk()
            ->assertSeeText('Još nema datoteka.');
    }

    #[DataProvider('listingCases')]
    public function test_excessive_page_redirects_to_last_existing_page(
        string $username,
        string $route
    ): void {
        $actor = User::where('username', $username)->firstOrFail();
        $owner = User::where('username', 'ivan')->firstOrFail();

        $documents = Document::factory()->count(11)->for($owner)->create();

        $this->actingAs($actor)
            ->get(route($route, ['page' => 99]))
            ->assertRedirect(route($route, ['page' => 3]));

        $response = $this->get(route($route, ['page' => 3]))
            ->assertOk();

        $paginator = $response->viewData('documents');

        $this->assertSame(11, $paginator->total());
        $this->assertSame(5, $paginator->perPage());
        $this->assertSame(3, $paginator->currentPage());

        // Najstariji dokument ostaje sam na trećoj stranici.
        $this->assertSame(
            [$documents->first()->id],
            $paginator->getCollection()->modelKeys()
        );
    }

    #[DataProvider('listingCases')]
    public function test_deleting_last_document_on_page_preserves_success_message(
        string $username,
        string $route
    ): void {
        $actor = User::where('username', $username)->firstOrFail();
        $owner = User::where('username', 'ivan')->firstOrFail();

        $documents = Document::factory()->count(6)->for($owner)->create();

        // Kod silaznog redoslijeda najstariji je na drugoj stranici.
        $document = $documents->first();

        Storage::disk('local')->put($document->path, 'Pozdrav');

        $secondPageUrl = route($route, ['page' => 2]);
        $firstPageUrl = route($route, ['page' => 1]);

        $this->actingAs($actor)
            ->from($secondPageUrl)
            ->delete(route('documents.destroy', $document))
            ->assertRedirect($secondPageUrl);

        $this->get($secondPageUrl)
            ->assertRedirect($firstPageUrl);

        $response = $this->get($firstPageUrl)
            ->assertOk()
            ->assertSeeText('Dokument je uspješno obrisan.')
            ->assertDontSeeText('Još nema datoteka.');

        $paginator = $response->viewData('documents');

        $this->assertSame(5, $paginator->total());
        $this->assertCount(5, $paginator->getCollection());

        $this->assertDatabaseMissing('documents', [
            'id' => $document->id,
        ]);

        Storage::disk('local')->assertMissing($document->path);
    }
}

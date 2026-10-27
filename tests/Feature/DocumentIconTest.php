<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentIconTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('iconCases')]
    public function test_extension_selects_expected_icon(
        ?string $extension,
        string $expectedIcon
    ): void {
        $document = new Document([
            'extension' => $extension,
        ]);

        $this->view('partials.document-icon', [
            'document' => $document,
        ])
            ->assertSee("bi bi-{$expectedIcon} me-2", false)
            ->assertSee('aria-hidden="true"', false);
    }

    public static function iconCases(): array
    {
        return [
            'pdf' => ['pdf', 'file-earmark-pdf'],
            'uppercase PDF' => ['PDF', 'file-earmark-pdf'],
            'word' => ['doc', 'file-earmark-word'],
            'word docx' => ['docx', 'file-earmark-word'],
            'spreadsheet' => ['xlsx', 'file-earmark-excel'],
            'csv' => ['csv', 'file-earmark-excel'],
            'image' => ['png', 'file-earmark-image'],
            'uppercase image' => ['JPG', 'file-earmark-image'],
            'audio' => ['mp3', 'file-earmark-music'],
            'archive' => ['zip', 'file-earmark-zip'],
            'text' => ['txt', 'file-earmark-text'],
            'unknown extension' => ['xyz', 'file-earmark'],
            'empty extension' => ['', 'file-earmark'],
            'no extension' => [null, 'file-earmark'],
        ];
    }

    public function test_icons_appear_on_both_document_listings(): void
    {
        $this->seed();

        $ivan = User::where('username', 'ivan')->firstOrFail();
        $admin = User::where('username', 'superadmin')->firstOrFail();

        Document::factory()->for($ivan)->create([
            'original_name' => 'primjer.pdf',
            'extension' => 'pdf',
        ]);

        foreach ([
            [$ivan, 'documents.index'],
            [$admin, 'admin.documents.index'],
        ] as [$user, $route]) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertOk()
                ->assertSeeText('primjer.pdf')
                ->assertSee('bi-file-earmark-pdf', false);
        }
    }
}

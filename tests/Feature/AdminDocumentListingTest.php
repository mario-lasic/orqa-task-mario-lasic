<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminDocumentListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_super_admin_sees_documents_with_correct_owners(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $ivan = User::where('username', 'ivan')->firstOrFail();
        $ana = User::where('username', 'ana')->firstOrFail();

        $anaDocument = Document::factory()->for($ana)->create([
            'original_name' => 'ana-document.txt',
        ]);

        $ivanDocument = Document::factory()->for($ivan)->create([
            'original_name' => 'ivan-document.txt',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.documents.index'))
            ->assertOk()
            ->assertViewIs('admin.documents.index')
            ->assertSeeText('Added By')
            ->assertSeeTextInOrder([
                'ivan-document.txt',
                'ivan',
                'ana-document.txt',
                'ana',
            ]);

        $documents = $response->viewData('documents')->getCollection();

        $this->assertSame(
            [$ivanDocument->id, $anaDocument->id],
            $documents->modelKeys()
        );

        foreach ($documents as $document) {
            $this->assertTrue($document->relationLoaded('user'));

            $this->assertSame(
                $document->user_id,
                $document->user->id
            );
        }
    }

    public function test_empty_listing_displays_empty_state(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.documents.index'))
            ->assertOk()
            ->assertSeeText('Još nema datoteka.')
            ->assertViewHas(
                'documents',
                fn ($documents) => $documents->total() === 0
            );
    }

    #[DataProvider('paginationCases')]
    public function test_listing_paginates_all_documents(
        int $total,
        int $page,
        array $expectedNumbers
    ): void {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $ivan = User::where('username', 'ivan')->firstOrFail();
        $ana = User::where('username', 'ana')->firstOrFail();

        $createdDocuments = [];

        for ($number = 1; $number <= $total; $number++) {
            $owner = $number % 2 === 0 ? $ana : $ivan;

            $createdDocuments[$number] = Document::factory()
                ->for($owner)
                ->create([
                    'original_name' => "file-{$number}.txt",
                ]);
        }

        $response = $this->actingAs($admin)
            ->get(route('admin.documents.index', ['page' => $page]))
            ->assertOk();

        $paginator = $response->viewData('documents');

        $expectedIds = array_map(
            fn (int $number) => $createdDocuments[$number]->id,
            $expectedNumbers
        );

        $this->assertSame($total, $paginator->total());
        $this->assertSame(5, $paginator->perPage());
        $this->assertSame($page, $paginator->currentPage());

        $this->assertSame(
            $expectedIds,
            $paginator->getCollection()->modelKeys()
        );

        foreach ($createdDocuments as $number => $document) {
            if (in_array($number, $expectedNumbers, true)) {
                $response->assertSeeText($document->original_name);
            } else {
                $response->assertDontSeeText($document->original_name);
            }
        }
    }

    public static function paginationCases(): array
    {
        return [
            'one document' => [1, 1, [1]],
            'five documents' => [5, 1, [5, 4, 3, 2, 1]],
            'six documents first page' => [6, 1, [6, 5, 4, 3, 2]],
            'six documents second page' => [6, 2, [1]],
            'eleven documents first page' => [11, 1, [11, 10, 9, 8, 7]],
            'eleven documents second page' => [11, 2, [6, 5, 4, 3, 2]],
            'eleven documents third page' => [11, 3, [1]],
        ];
    }

    public function test_guest_cannot_access_admin_document_listing(): void
    {
        $this->get(route('admin.documents.index'))
            ->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_access_admin_document_listing(): void
    {
        $user = User::where('username', 'ivan')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.documents.index'))
            ->assertForbidden();
    }

    public function test_user_with_both_roles_can_see_other_users_documents(): void
    {
        $marko = User::where('username', 'marko')->firstOrFail();
        $ana = User::where('username', 'ana')->firstOrFail();

        $document = Document::factory()->for($ana)->create([
            'original_name' => 'ana-private.txt',
        ]);

        $this->actingAs($marko)
            ->get(route('admin.documents.index'))
            ->assertOk()
            ->assertSeeText('ana-private.txt')
            ->assertSee(route('documents.download', $document))
            ->assertSee(route('documents.destroy', $document));
    }

    public function test_super_admin_sees_download_and_delete_actions(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $ivan = User::where('username', 'ivan')->firstOrFail();

        $document = Document::factory()->for($ivan)->create();

        $this->actingAs($admin)
            ->get(route('admin.documents.index'))
            ->assertOk()
            ->assertSeeText('Preuzmi')
            ->assertSeeText('Obriši')
            ->assertSee(route('documents.download', $document))
            ->assertSee(route('documents.destroy', $document));
    }

    public function test_success_message_is_displayed_on_admin_listing(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $this->actingAs($admin)
            ->withSession([
                'success' => 'Dokument je uspješno obrisan.',
            ])
            ->get(route('admin.documents.index'))
            ->assertOk()
            ->assertSeeText('Dokument je uspješno obrisan.');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_their_documents(): void
    {
        $this->seed();

        $ivan = User::where('username', 'ivan')->firstOrFail();
        $ana = User::where('username', 'ana')->firstOrFail();

        Document::factory()->for($ivan)->create([
            'original_name' => 'ivan-file.txt',
        ]);

        Document::factory()->for($ana)->create([
            'original_name' => 'ana-file.txt',
        ]);

        $this->actingAs($ivan)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSeeText('ivan-file.txt')
            ->assertDontSeeText('ana-file.txt');
    }

    public function test_first_page_displays_five_newest_documents(): void
    {
        $this->seed();
        $user = User::where('username', 'ivan')->firstOrFail();

        $this->createDocumentsFor($user, 6);

        $response = $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertOk();

        foreach (range(2, 6) as $number) {
            $response->assertSeeText("file-{$number}.txt");
        }

        $response->assertDontSeeText('file-1.txt');
    }

    public function test_second_page_displays_remaining_document(): void
    {
        $this->seed();
        $user = User::where('username', 'ivan')->firstOrFail();

        $this->createDocumentsFor($user, 6);

        $response = $this->actingAs($user)
            ->get(route('documents.index', ['page' => 2]))
            ->assertOk()
            ->assertSeeText('file-1.txt');

        foreach (range(2, 6) as $number) {
            $response->assertDontSeeText("file-{$number}.txt");
        }
    }

    private function createDocumentsFor(User $user, int $count): void
    {
        for ($number = 1; $number <= $count; $number++) {
            Document::factory()->for($user)->create([
                'original_name' => "file-{$number}.txt",
            ]);
        }
    }
}

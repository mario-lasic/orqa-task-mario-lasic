<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_name' => fake()->word() . '.txt',
            'path' => 'documents/' . Str::uuid() . '.txt',
            'size_bytes' => fake()->numberBetween(1_024, 1_000_000),
            'extension' => 'txt'
        ];
    }
}

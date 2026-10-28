<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userRole = Role::where('name', 'user')->firstOrFail();
        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $attributes = User::factory()->raw([
            'username' => 'ivan',
            'name' => 'Ivan',
            'email' => 'ivan@gmail.com',
        ]);
        $markoAttributes = User::factory()->raw([
            'username' => 'marko',
            'name' => 'Marko',
            'email' => 'marko@gmail.com',
        ]);
        $anaAttributes = User::factory()->raw([
            'username' => 'ana',
            'name' => 'Ana',
            'email' => 'ana@gmail.com',
        ]);
        $superAttributes = User::factory()->raw([
            'username' => 'superadmin',
            'name' => 'SuperAdmin',
            'email' => 'superadmin@gmail.com',
        ]);
        $user = User::firstOrCreate(['username' => 'ivan'], $attributes);
        $marko = User::firstOrCreate(['username' => 'marko'], $markoAttributes);
        $ana = User::firstOrCreate(['username' => 'ana'], $anaAttributes);
        $super = User::firstOrCreate(['username' => 'superadmin'], $superAttributes);
        $user->roles()->syncWithoutDetaching([$userRole->id]);
        $marko->roles()->syncWithoutDetaching([$superAdminRole->id, $userRole->id]);
        $ana->roles()->syncWithoutDetaching([$userRole->id]);
        $super->roles()->syncWithoutDetaching([$superAdminRole->id]);
    }
}

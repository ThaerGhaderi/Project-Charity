<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
       $this->call([
        CitySeeder::class,
        DomainSeeder::class,
        DaySeeder::class,
        LanguageSeeder::class,
        SkillSeeder::class,
        CategorySeeder::class,
        TypeSeeder::class,
            DonorSeeder::class,
            CampaignSeeder::class,
            RandomDonationsSeeder::class,
     ]);
        /*User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);*/
    }
}

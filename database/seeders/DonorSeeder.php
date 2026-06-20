<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Profile;
use App\Models\DonorProfile;
use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str; 

class DonorSeeder extends Seeder
{
    public function run(): void
    {
        // التأكد من وجود مدن
        $cities = City::all();
        if ($cities->isEmpty()) {
            $this->command->warn('⚠️ No cities found. Please run CitySeeder first.');
            return;
        }

        $numberOfDonors = $this->command->ask('How many donors to create?', 20);
        $numberOfDonors = (int) $numberOfDonors;

        $this->command->info("👤 Creating {$numberOfDonors} donors...");

        for ($i = 0; $i < $numberOfDonors; $i++) {
            $city = $cities->random();
            $gender = fake()->randomElement(['ذكر', 'انثى']);

            $user = User::create([
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'password' => Hash::make('password123'),
                'role' => 'Donor',
                'is_verified' => true,
                'is_active' => true,
                'profile_completed' => true,
                'email_verified_at' => now(),
            ]);

            $profile = Profile::create([
                'user_id' => $user->id,
                'city_id' => $city->id,
                'photo_id' => 'dummy_photo_id_' . Str::random(8),
                'phone' => '+963' . fake()->randomNumber(9),
                'birth_date' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
                'gender' => $gender,
                'bio' => fake()->sentence(),
            ]);

            $totalDonated = fake()->randomFloat(2, 0, 1000);
            $loyaltyPoints = (int) $totalDonated;

            DonorProfile::create([
                'user_id' => $user->id,
                'donor_type' => fake()->randomElement(['فردي', 'منظمة']),
                'is_anonymous' => fake()->boolean(10),
                'total_donated' => $totalDonated,
                'loyalty_points' => $loyaltyPoints,
                'loyalty_tier' => $this->getLoyaltyTier($loyaltyPoints),
                'bio' => fake()->sentence(),
            ]);
        }

        $this->command->info("✅ Successfully created {$numberOfDonors} donors!");
    }

    private function getLoyaltyTier($points): ?string
    {
        if ($points >= 3000) return 'ذهبية';
        if ($points >= 1000) return 'فضية';
        if ($points >= 300) return 'برونزية';
        return null;
    }
}
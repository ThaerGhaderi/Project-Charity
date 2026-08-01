<?php
// database/seeders/VolunteerSeeder.php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Profile;
use App\Models\VolunterProfile;
use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class VolunteerSeeder extends Seeder
{
    public function run(): void
    {
        // التحقق من وجود مدن
        $cities = City::all();
        if ($cities->isEmpty()) {
            $this->command->warn(' لا توجد مدن. يرجى تشغيل CitySeeder أولاً.');
            return;
        }

        $numberOfVolunteers = $this->command->ask('كم عدد المتطوعين الذي تريد إنشاؤهم؟', 15);
        $numberOfVolunteers = (int) $numberOfVolunteers;

        $this->command->info("👤 جاري إنشاء {$numberOfVolunteers} متطوع...");

        $statuses = ['منشغل', 'متاح', 'غير متاح'];
        $periods = ['صباحاً', 'ظهراً', 'مساءً'];
        $commitmentTypes = ['منتظم', 'مرة بمرة'];
        $educationLevels = ['ثانوية عامة', 'بكالوريوس', 'ماستر', 'دكتوراة', 'معهد'];

        for ($i = 0; $i < $numberOfVolunteers; $i++) {
            $city = $cities->random();
            $gender = fake()->randomElement(['ذكر', 'انثى']);

            // إنشاء المستخدم
            $user = User::create([
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'password' => Hash::make('password123'),
                'role' => 'volunteer',
                'is_verified' => true,
                'is_active' => true,
                'profile_completed' => true,
                'email_verified_at' => now(),
            ]);

            // إنشاء الملف الشخصي العام
            $profile = Profile::create([
                'user_id' => $user->id,
                'city_id' => $city->id,
                'photo_id' => null,
                'phone' => '+963' . fake()->randomNumber(9),
                'birth_date' => fake()->dateTimeBetween('-50 years', '-18 years')->format('Y-m-d'),
                'gender' => $gender,
                'bio' => fake()->sentence(),
            ]);

            // إنشاء ملف المتطوع
            VolunterProfile::create([
                'user_id' => $user->id,
                'Favorite_period' => fake()->randomElement($periods),
                'total_hours' => fake()->numberBetween(0, 200),
                'previous_voluntering' => fake()->boolean(70),
                'previous_work_place' => fake()->boolean(70) ? fake()->company() : null,
                'experience_years' => fake()->numberBetween(0, 10),
                'car' => fake()->boolean(40),
                'status' => fake()->randomElement($statuses),
                'bio' => fake()->sentence(),
                'Commitment_type' => fake()->randomElement($commitmentTypes),
                'Educational_level' => fake()->randomElement($educationLevels),
                'facebook' => fake()->boolean(50) ? 'https://facebook.com/' . fake()->userName() : null,
                'linkedin' => fake()->boolean(30) ? 'https://linkedin.com/in/' . fake()->userName() : null,
            ]);
        }

        $this->command->info("تم إنشاء {$numberOfVolunteers} متطوع بنجاح!");
    }
}

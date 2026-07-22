<?php
// database/seeders/BeneficiarySeeder.php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Profile;
use App\Models\BeneficiaryProfile;
use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BeneficiarySeeder extends Seeder
{
    public function run(): void
    {
        // التحقق من وجود مدن
        $cities = City::all();
        if ($cities->isEmpty()) {
            $this->command->warn(' لا توجد مدن. يرجى تشغيل CitySeeder أولاً.');
            return;
        }

        $numberOfBeneficiaries = $this->command->ask('كم عدد المستفيدين الذي تريد إنشاؤهم؟', 20);
        $numberOfBeneficiaries = (int) $numberOfBeneficiaries;

        $this->command->info("👤 جاري إنشاء {$numberOfBeneficiaries} مستفيد...");

        $maritalStatuses = ['أعزب', 'متزوج', 'مطلق', 'أرمل', 'يتيم'];
        $incomeRanges = ['أقل من 100 الف', '100-300 الف', '300-500 الف', 'أكثر من 500 الف'];

        for ($i = 0; $i < $numberOfBeneficiaries; $i++) {
            $city = $cities->random();
            $gender = fake()->randomElement(['ذكر', 'انثى']);

            // إنشاء المستخدم
            $user = User::create([
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'password' => Hash::make('password123'),
                'role' => 'Beneficiary',
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
                'birth_date' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
                'gender' => $gender,
                'bio' => fake()->sentence(),
            ]);

            $hasIncome = fake()->boolean(40);
            $familyMembers = fake()->numberBetween(1, 8);

            // إنشاء ملف المستفيد
            BeneficiaryProfile::create([
                'user_id' => $user->id,
                'priority_score' => fake()->numberBetween(0, 100),
                'family_members_count' => $familyMembers,
                'Breadwinner' => fake()->boolean(60),
                'has_income' => $hasIncome,
                'income_range' => $hasIncome ? fake()->randomElement($incomeRanges) : null,
                'photo_Family_notebook' => null,
                'photo_Supporting' => null,
                'marital_status' => fake()->randomElement($maritalStatuses),
                'is_Anonymous' => fake()->boolean(10),
            ]);
        }

        $this->command->info(" تم إنشاء {$numberOfBeneficiaries} مستفيد بنجاح!");
    }
}
<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // ✅ الجداول المرجعية
            CitySeeder::class,
            DomainSeeder::class,
            DaySeeder::class,
            LanguageSeeder::class,
            SkillSeeder::class,
            CategorySeeder::class,
            TypeSeeder::class,
            
            // ✅ المستخدمين
            DonorSeeder::class,
             BeneficiarySeeder::class, // إذا كان موجوداً
             VolunteerSeeder::class,   // إذا كان موجوداً
            
            // ✅ الحملات
            CampaignSeeder::class,
            
            // ✅ التبرعات
            RandomDonationsSeeder::class,
            
            // ✅ المهام التطوعية (جديد)
            VolunteerTaskSeeder::class,
        ]);
    }
}
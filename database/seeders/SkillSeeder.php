<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $skills = [
            'توزيع سلل غذائية',
            'زيارات ميدانية',
            'ادارة فعاليات',
            'توثيق ميداني',
            'مساعدة الاسر',
            'تنظيم متطوعين',
        ];
        foreach ($skills as $skill) {
            Skill::create(['name' => $skill]);
        }
    }
}

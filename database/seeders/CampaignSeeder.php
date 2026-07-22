<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        if (!$admin) {
            $this->command->warn('⚠️ No admin user found. Please create an admin first.');
            return;
        }

        $campaigns = [
            [
                'title' => 'علاج أطفال السرطان',
                'description' => 'تهدف هذه الحملة إلى تأمين العلاج الكيميائي لـ 24 طفلاً يعانون من سرطان الدم في مستشفى الأطفال.',
                'goal_amount' => 20000,
                'category' => 'صحة',
                'is_emergency' => false,
                'status' => 'active',
            ],
            [
                'title' => 'دعم تعليم الأيتام',
                'description' => 'توفير مستلزمات مدرسية ورسوم دراسية لـ 50 يتيماً في مدارس جمعية الأمل.',
                'goal_amount' => 15000,
                'category' => 'تعليم',
                'is_emergency' => false,
                'status' => 'active',
            ],
            [
                'title' => 'إغاثة متضرري الزلزال',
                'description' => 'توفير مأوى وطعام ودواء للمتضررين من الزلزال في المناطق المنكوبة.',
                'goal_amount' => 50000,
                'category' => 'إطعام',
                'is_emergency' => true,
                'status' => 'active',
            ],
            [
                'title' => 'إعادة بناء المنازل المدمرة',
                'description' => 'إعادة بناء 20 منزلاً دمرت بالكامل في المناطق المتضررة.',
                'goal_amount' => 30000,
                'category' => 'مساجد',
                'is_emergency' => false,
                'status' => 'active',
            ],
            [
                'title' => 'توفير مياه الشرب النقية',
                'description' => 'حفر آبار وتوفير مياه شرب نظيفة لـ 10 قرى محرومة.',
                'goal_amount' => 25000,
                'category' => 'مياه',
                'is_emergency' => false,
                'status' => 'active',
            ],
            [
                'title' => 'رعاية الأيتام',
                'description' => 'تأمين احتياجات 100 يتيم من غذاء وكسوة وتعليم.',
                'goal_amount' => 35000,
                'category' => 'أيتام',
                'is_emergency' => false,
                'status' => 'active',
            ],
        ];

        $this->command->info("📢 Creating " . count($campaigns) . " campaigns...");

        foreach ($campaigns as $campaignData) {
            Campaign::create([
                'title' => $campaignData['title'],
                'description' => $campaignData['description'],
                'goal_amount' => $campaignData['goal_amount'],
                'collected_amount' => fake()->randomFloat(2, 0, $campaignData['goal_amount'] * 0.8),
                'category' => $campaignData['category'],
                'is_emergency' => $campaignData['is_emergency'],
                'status' => $campaignData['status'],
                'short_url' => Str::random(8),
                'qr_code_url' => 'qrcodes/' . Str::random(10) . '.png',
                'start_date' => now()->subDays(random_int(1, 30)),
                'end_date' => now()->addDays(random_int(30, 90)),
                'created_by' => $admin->id,
            ]);
        }

        $this->command->info("✅ Campaigns created successfully!");
    }
}
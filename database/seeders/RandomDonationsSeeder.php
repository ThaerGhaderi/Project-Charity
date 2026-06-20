<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\PaymentTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class RandomDonationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // 1. التأكد من وجود مستخدمين وحملات
        $users = User::where('role', 'Donor')->where('profile_completed', true)->get();
        $campaigns = Campaign::where('status', 'active')->get();

        if ($users->isEmpty()) {
            $this->command->warn('⚠️ No donors found. Please create donors first.');
            $this->command->warn('   You can run: php artisan db:seed --class=UserSeeder');
            return;
        }

        if ($campaigns->isEmpty()) {
            $this->command->warn('⚠️ No campaigns found. Please create campaigns first.');
            $this->command->warn('   You can run: php artisan db:seed --class=CampaignSeeder');
            return;
        }

        // 2. إعدادات الـ seeder
        $numberOfDonations = $this->command->ask('How many donations to create?', 50);
        $numberOfDonations = (int) $numberOfDonations;

        $this->command->info("🚀 Creating {$numberOfDonations} random donations...");

        $progressBar = $this->command->getOutput()->createProgressBar($numberOfDonations);
        $progressBar->start();

        $donationStatuses = ['pending', 'completed', 'failed', 'refunded'];
        $paymentMethods = ['stripe', 'paypal', 'tap', 'moyasar', 'mada', 'apple_pay', 'google_pay'];
        $currencies = ['USD', 'EUR', 'SAR', 'AED', 'GBP'];
        $gateways = ['local', 'payerurl'];

        $donationsCreated = 0;

        for ($i = 0; $i < $numberOfDonations; $i++) {
            // اختيار مستخدم وحملة عشوائية
            $user = $users->random();
            $campaign = $campaigns->random();

            // بيانات عشوائية
            $amount = $faker->randomFloat(2, 5, 500);
            $status = $faker->randomElement($donationStatuses);
            $paymentMethod = $faker->randomElement($paymentMethods);
            $currency = $faker->randomElement($currencies);
            $paymentGateway = $faker->randomElement($gateways);
            $isAnonymous = $faker->boolean(20);
            $isRecurring = $faker->boolean(10);
            $isGift = $faker->boolean(5);

            // تاريخ عشوائي خلال الـ 3 أشهر الماضية
            $donatedAt = $faker->dateTimeBetween('-3 months', 'now');

            try {
                DB::beginTransaction();

                // إنشاء التبرع
                $donation = Donation::create([
                    'user_id' => $user->id,
                    'campaign_id' => $campaign->id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_method' => $paymentMethod,
                    'payment_gateway' => $paymentGateway,
                    'status' => $status,
                    'gateway_status' => $status === 'completed' ? 'completed' : null,
                    'is_anonymous' => $isAnonymous,
                    'is_recurring' => $isRecurring,
                    'is_gift' => $isGift,
                    'on_behalf_of' => $isGift ? $faker->name() : null,
                    'gift_message' => $isGift ? $faker->sentence() : null,
                    'donated_at' => $donatedAt,
                    'created_at' => $donatedAt,
                    'updated_at' => $donatedAt,
                ]);

                // إنشاء معاملة دفع إذا كان التبرع مكتملاً
                if ($status === 'completed') {
                    PaymentTransaction::create([
                        'donation_id' => $donation->id,
                        'gateway_ref' => 'TXN_' . Str::random(16),
                        'amount' => $amount,
                        'currency' => $currency,
                        'status' => 'success',
                        'gateway_response' => json_encode(['status' => 'completed', 'message' => 'Payment successful']),
                        'processed_at' => $donatedAt,
                        'created_at' => $donatedAt,
                        'updated_at' => $donatedAt,
                    ]);

                    // تحديث المبلغ المجمع في الحملة
                    $campaign->collected_amount += $amount;
                    $campaign->save();

                    // تحديث ملف المتبرع
                    if ($user->donor) {
                        $user->donor->total_donated += $amount;
                        $user->donor->loyalty_points += (int) $amount;
                        
                        if (method_exists($user->donor, 'updateLoyaltyTier')) {
                            $user->donor->updateLoyaltyTier();
                        } else {
                            $points = $user->donor->loyalty_points;
                            if ($points >= 3000) {
                                $user->donor->loyalty_tier = 'ذهبية';
                            } elseif ($points >= 1000) {
                                $user->donor->loyalty_tier = 'فضية';
                            } elseif ($points >= 300) {
                                $user->donor->loyalty_tier = 'برونزية';
                            }
                        }
                        $user->donor->save();
                    }
                }

                DB::commit();
                $donationsCreated++;

            } catch (\Exception $e) {
                DB::rollBack();
                $this->command->warn("❌ Failed to create donation: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine(2);
        $this->command->info("✅ Successfully created {$donationsCreated} random donations!");

        // عرض إحصائيات
        $this->showStatistics();
    }

    /**
     * عرض إحصائيات بعد الإنشاء
     */
    private function showStatistics(): void
    {
        $totalDonations = Donation::count();
        $totalCompleted = Donation::where('status', 'completed')->count();
        $totalAmount = Donation::where('status', 'completed')->sum('amount');
        $totalCampaigns = Campaign::count();
        $totalDonors = User::where('role', 'Donor')->count();

        $this->command->newLine();
        $this->command->info("📊 Statistics:");
        $this->command->line("   📋 Total Donations: {$totalDonations}");
        $this->command->line("   ✅ Completed: {$totalCompleted}");
        $this->command->line("   💰 Total Amount: $" . number_format($totalAmount, 2));
        $this->command->line("   📢 Campaigns: {$totalCampaigns}");
        $this->command->line("   👤 Donors: {$totalDonors}");
        $this->command->newLine();

        // عرض أفضل 5 حملات
        $topCampaigns = Campaign::withCount(['donations as total_donations' => function($q) {
                $q->where('status', 'completed');
            }])
            ->withSum(['donations as total_amount' => function($q) {
                $q->where('status', 'completed');
            }], 'amount')
            ->orderBy('total_amount', 'desc')
            ->limit(5)
            ->get();

        $this->command->info("🏆 Top 5 Campaigns:");
        foreach ($topCampaigns as $campaign) {
            $amount = $campaign->total_amount ?? 0;
            $donations = $campaign->total_donations ?? 0;
            $this->command->line("   📌 {$campaign->title}: $" . number_format($amount, 2) . " ({$donations} donations)");
        }

        // عرض أفضل 5 متبرعين
        $topDonors = User::whereHas('donor')
            ->with('donor')
            ->get()
            ->sortByDesc(function($user) {
                return $user->donor->total_donated ?? 0;
            })
            ->take(5);

        $this->command->newLine();
        $this->command->info("🥇 Top 5 Donors:");
        foreach ($topDonors as $user) {
            $total = $user->donor->total_donated ?? 0;
            $tier = $user->donor->loyalty_tier ?? 'بدون';
            $this->command->line("   👤 {$user->name}: $" . number_format($total, 2) . " ({$tier})");
        }
    }
}
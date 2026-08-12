<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Models\BeneficiaryProfile;
use App\Models\VolunterProfile;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    protected array $arabicMonths = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];
    protected array $palette = [
        '#5B8DEF', '#FF7A7A', '#34D399', '#FBBF24',
        '#A78BFA', '#F472B6', '#38BDF8', '#F97316',
    ];


    protected array $sourceMap = [
        'stripe'     => 'بطاقة ائتمان',
        'mada'       => 'مدى',
        'apple_pay'  => 'Apple Pay',
        'google_pay' => 'Google Pay',
        'paypal'     => 'PayPal',
        'tap'        => 'تطبيق',
        'moyasar'    => 'موقع',
        'crypto'     => 'عملات رقمية',
        'payerurl'   => 'رابط دفع',
    ];
    protected array $beneficiaryStatusMap = [
        'مقبول'        => 'نشط',
        'قيد المراجعة' => 'غير نشط',
        'مرفوض'        => 'غير نشط',
    ];
    protected array $volunteerStatusMap = [
        'متاح'     => 'نشط',
        'مشغول'    => 'نشط',
        'غير متاح' => 'غير نشط',
    ];
    protected function monthlyDonations(int $months = 6)
    {
        $rows = Donation::where('status', 'completed')
            ->where('donated_at', '>=', now()->subMonths($months - 1)->startOfMonth())
            ->selectRaw('MONTH(donated_at) as month, YEAR(donated_at) as year, SUM(amount) as amt')
            ->groupBy('year', 'month')
            ->orderBy('year')->orderBy('month')
            ->get();

        return $rows->map(fn ($row) => [
            'm'   => $this->arabicMonths[$row->month] ?? (string) $row->month,
            'amt' => (float) $row->amt,
        ])->values();
    }
    protected function donationsByCategory()
    {
        $rows = Donation::join('campaigns', 'campaigns.id', '=', 'donations.campaign_id')
            ->where('donations.status', 'completed')
            ->selectRaw('campaigns.category as name, SUM(donations.amount) as total')
            ->groupBy('campaigns.category')
            ->get();

        $sum = $rows->sum('total') ?: 1;

        return $rows->values()->map(function ($row, $i) use ($sum) {
            return [
                'name'  => $row->name,
                'value' => (int) round(($row->total / $sum) * 100),
                'color' => $this->palette[$i % count($this->palette)],
            ];
        });
    }
    protected function donationsBySource()
    {
        $rows = Donation::where('status', 'completed')
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->get();

        $sum = $rows->sum('total') ?: 1;

        return $rows->values()->map(function ($row, $i) use ($sum) {
            return [
                'name'  => $this->sourceMap[$row->payment_method] ?? $row->payment_method,
                'value' => (int) round(($row->total / $sum) * 100),
                'color' => $this->palette[$i % count($this->palette)],
            ];
        });
    }
    protected function topDonors(int $limit = 10)
    {
        $donors = Donation::join('donor_profiles', 'donor_profiles.id', '=', 'donations.donor_id')
            ->join('users', 'users.id', '=', 'donor_profiles.user_id')
            ->where('donations.status', 'completed')
            ->selectRaw('donations.donor_id, users.name as donor, SUM(donations.amount) as amount')
            ->groupBy('donations.donor_id', 'users.name')
            ->orderByDesc('amount')
            ->limit($limit)
            ->get();

        return $donors->map(function ($row) {
            $lastDonation = Donation::where('donor_id', $row->donor_id)
                ->where('status', 'completed')
                ->latest('donated_at')
                ->first();

            $topCategory = Donation::join('campaigns', 'campaigns.id', '=', 'donations.campaign_id')
                ->where('donations.donor_id', $row->donor_id)
                ->where('donations.status', 'completed')
                ->selectRaw('campaigns.category as cat, SUM(donations.amount) as total')
                ->groupBy('campaigns.category')
                ->orderByDesc('total')
                ->first();

            return [
                'donor'  => $row->donor,
                'amount' => (float) $row->amount,
                'method' => $this->sourceMap[$lastDonation->payment_method ?? ''] ?? ($lastDonation->payment_method ?? null),
                'cat'    => $topCategory->cat ?? null,
            ];
        })->values();
    }
    public function general()
    {
        return response()->json([
            'monthly'    => $this->monthlyDonations(),
            'categories' => $this->donationsByCategory(),
            'sources'    => $this->donationsBySource(),
            'topDonors'  => $this->topDonors(10),
        ]);
    }
    public function donations(Request $request)
    {
        $totalAmount    = (float) Donation::where('status', 'completed')->sum('amount');
        $totalDonations = Donation::where('status', 'completed')->count();
        $averageDonation = $totalDonations > 0 ? $totalAmount / $totalDonations : 0;

        $rows = Donation::join('donor_profiles', 'donor_profiles.id', '=', 'donations.donor_id')
            ->join('users', 'users.id', '=', 'donor_profiles.user_id')
            ->join('campaigns', 'campaigns.id', '=', 'donations.campaign_id')
            ->where('donations.status', 'completed')
            ->orderByDesc('donations.donated_at')
            ->selectRaw('
                donations.id,
                users.name as donor,
                donations.amount,
                donations.payment_method,
                campaigns.category,
                donations.donated_at
            ')
            ->limit(500)
            ->get();

        $donations = $rows->map(function ($row) {
            return [
                'id'       => $row->id,
                'donor'    => $row->donor,
                'amount'   => (float) $row->amount,
                'method'   => $this->sourceMap[$row->payment_method] ?? $row->payment_method,
                'category' => $row->category,
                'date'     => Carbon::parse($row->donated_at)->format('Y-m-d'),
            ];
        });

        return response()->json([
            'summary' => [
                'totalAmount'     => $totalAmount,
                'totalDonations'  => $totalDonations,
                'averageDonation' => round($averageDonation, 2),
            ],
            'monthly'    => $this->monthlyDonations(),
            'categories' => $this->donationsByCategory(),
            'sources'    => $this->donationsBySource(),
            'donations'  => $donations,
        ]);
    }
    public function beneficiaries()
    {
        $total    = BeneficiaryProfile::count();
        $active   = BeneficiaryProfile::where('status', 'مقبول')->count();
        $inactive = $total - $active;

        $byCategory = BeneficiaryProfile::join('beneficiary_types', 'beneficiary_types.beneficiary_profile_id', '=', 'beneficiary_profiles.id')
            ->join('types', 'types.id', '=', 'beneficiary_types.type_id')
            ->selectRaw('types.name as category, COUNT(DISTINCT beneficiary_profiles.id) as count')
            ->groupBy('types.name')
            ->get();

        $monthly = BeneficiaryProfile::where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw('MONTH(created_at) as month, YEAR(created_at) as year, COUNT(*) as count')
            ->groupBy('year', 'month')
            ->orderBy('year')->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'm'     => $this->arabicMonths[$r->month] ?? (string) $r->month,
                'count' => $r->count,
            ]);
        $list = BeneficiaryProfile::with(['user', 'types'])->get()->map(function ($b) {
            return [
                'id'       => $b->id,
                'name'     => $b->user->name ?? null,
                'category' => optional($b->types->first())->name,
                'status'   => $this->beneficiaryStatusMap[$b->status] ?? $b->status,
            ];
        });

        return response()->json([
            'summary' => [
                'total'    => $total,
                'active'   => $active,
                'inactive' => $inactive,
            ],
            'byCategory'    => $byCategory,
            'monthly'       => $monthly,
            'beneficiaries' => $list,
        ]);
    }
    public function volunteers()
    {
        $total  = VolunterProfile::count();
        $active = VolunterProfile::whereIn('status', ['متاح', 'مشغول'])->count();
        $hours  = (int) VolunterProfile::sum('total_hours');

        $monthly = [];

        $topVolunteers = VolunterProfile::with('user')
            ->orderByDesc('total_hours')
            ->limit(10)
            ->get()
            ->map(function ($v) {
                return [
                    'id'    => $v->id,
                    'name'  => $v->user->name ?? null,
                    'hours' => $v->total_hours,
                    'tasks' => $v->tasks()->count(),
                ];
            });

        $list = VolunterProfile::with('user')->get()->map(function ($v) {
            return [
                'id'     => $v->id,
                'name'   => $v->user->name ?? null,
                'status' => $this->volunteerStatusMap[$v->status] ?? $v->status,
            ];
        });

        return response()->json([
            'summary' => [
                'total'  => $total,
                'active' => $active,
                'hours'  => $hours,
            ],
            'monthly'       => $monthly,
            'topVolunteers' => $topVolunteers,
            'volunteers'    => $list,
        ]);
    }
}

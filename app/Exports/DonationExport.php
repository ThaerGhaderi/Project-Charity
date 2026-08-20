<?php

namespace App\Exports;

use App\Models\Donation;
use App\Models\Doration;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DonationExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // 1. جلب التبرعات من جدول Donations وتوحيد الحقول
        $donations = Donation::with(['donor.user', 'campaign'])->get()->map(function ($item) {
            // نضع البيانات في خصائص موحدة لكي يفهمها دالة map بسهولة
            $item->unified_donor = $item->donor?->user?->name ?? 'غير معروف';
            $item->unified_category = $item->campaign?->category ?? ($item->cat ?? 'غير محدد');
            $item->unified_date = $item->created_at->format('Y-m-d');
            $item->unified_notes = $item->description ?? '-';
            return $item;
        });

        // 2. جلب التبرعات من جدول Dorations وتوحيد الحقول
        $dorations = Doration::with('donorProfile.user')->get()->map(function ($item) {
            $item->unified_donor = $item->donorProfile && $item->donorProfile->user ? $item->donorProfile->user->name : 'متبرع غير معروف';
            $item->unified_category = $item->cat ?? 'غير محدد';
            $item->unified_date = $item->date;
            $item->unified_notes = $item->notes ?? '-';
            return $item;
        });

        // 3. دمج المجموعتين
        return $donations->concat($dorations);
    }

    public function headings(): array
    {
        return [
            'رقم التبرع',
            'اسم المتبرع كامل',
            'المبلغ (ل.س)',
            'طريقة الدفع',
            'الحالة',
            'القسم (الفئة)',
            'تاريخ التبرع',
            'ملاحظات'
        ];
    }

    public function map($donation): array
    {
        return [
            $donation->id,
            $donation->unified_donor,
            $donation->amount,
            $donation->payment_method,
            $donation->status,
            $donation->unified_category,
            $donation->unified_date,
            $donation->unified_notes,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // رقم التبرع
            'B' => 25,  // اسم المتبرع
            'C' => 15,  // المبلغ (تمت إضافته لأنه كان مفقوداً في كودك)
            'D' => 18,  // طريقة الدفع
            'E' => 15,  // الحالة
            'F' => 15,  // الفئة
            'G' => 18,  // التاريخ
            'H' => 25,  // ملاحظات
        ];
    }
}
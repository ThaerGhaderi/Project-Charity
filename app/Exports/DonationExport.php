<?php

namespace App\Exports;
use App\Models\Doration;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;


class DonationExport implements FromCollection,WithHeadings, WithMapping,WithColumnWidths
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Doration::with('donorProfile')->get();
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
            $donation->donorProfile && $donation->donorProfile->user?$donation->donorProfile->user->name:'متبرع غير معروف',
            $donation->amount,
            $donation->payment_method,
            $donation->status,
            $donation->cat,
            $donation->date,
            $donation->notes ?? '-',
        ];
    }
    public function columnWidths(): array
    {
        return [
            'A' => 12,  // رقم التبرع
            'B' => 25,  // اسم المتبرع
            'D' => 15,  // المبلغ
            'E' => 18,  // طريقة الدفع
            'F' => 15,  // الحالة
            'G' => 15,  // الفئة
            'H' => 18,  // التاريخ
            'I' => 25,  // ملاحظات
        ];
    }
}
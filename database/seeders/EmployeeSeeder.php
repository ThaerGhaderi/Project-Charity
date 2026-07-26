<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmployeeSeeder extends Seeder
{


    public function run(): void
    {
        $employees = [
            [
                'full_name' => 'ثائر غادري',
                'password'  => Hash::make('password123'),
                'role'      => 'مدير',
            ],
            [
                'full_name' => 'محمد أبي جابر',
                'password'  => Hash::make('password123'),
                'role'      => 'محاسب',
            ],
            [
                'full_name' => 'عمر ياسين',
                'password'  => Hash::make('password123'),
                'role'      => 'موظف',
            ],
            [
                'full_name' => 'منى يوسف',
                'password'  => Hash::make('password123'),
                'role'      => 'مشاهد',
            ],
        ];

        foreach ($employees as $employee) {
            Employee::create($employee);
        }
    }
}

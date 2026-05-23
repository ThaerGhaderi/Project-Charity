<?php

namespace Database\Seeders;

use App\Models\Type;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
        'مسكن و غذاء',
        'علاج طبي',
        'تعليم أبناء',
        'دعم مالي',
        'إطعام',
    ];
    foreach ($types as $type) {
        Type::create(['name' => $type]);
    }
    }
}

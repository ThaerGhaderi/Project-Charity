<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
        'دمشق', 'حلب', 'حمص', 'حماة', 'اللاذقية',
        'طرطوس', 'ادلب', 'درعا', 'دير الزور', 'الحسكة',
        'الرقة', 'السويداء', 'القنيطرة', 'ريف دمشق'
    ];
    foreach ($cities as $city) {
       City::create(['name' => $city]);
    }
    }
}

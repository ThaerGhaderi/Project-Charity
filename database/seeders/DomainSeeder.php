<?php

namespace Database\Seeders;

use App\Models\Domain;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DomainSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $domains = ['إعلامي', 'تقني', 'تعليمي', 'إداري', 'ميداني'];
        foreach ($domains as $domain) {
            Domain::create(['name' => $domain]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Support\Home\HomeSectionDefaults;
use Illuminate\Database\Seeder;

/**
 * Today's homepage layout as editable home_sections rows (no-op when sections already exist —
 * the create_home_sections migration normally installs them already).
 */
class HomeSectionSeeder extends Seeder
{
    public function run(): void
    {
        HomeSectionDefaults::install();
    }
}

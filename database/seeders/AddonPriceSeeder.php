<?php

namespace Database\Seeders;

use App\Models\AddonPrice;
use Illuminate\Database\Seeder;

class AddonPriceSeeder extends Seeder
{
    public function run(): void
    {
        AddonPrice::query()->firstOrCreate(
            ['code' => 'odontogram'],
            ['name' => 'Odontogram', 'monthly_amount' => 99_000, 'is_active' => true],
        );
    }
}

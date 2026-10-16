<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            'free' => [
                'name' => 'Free',
                'doctor_limit' => 1,
                'staff_limit' => 1,
                'monthly_visit_limit' => 50,
                'features' => [],
                'prices' => [],
            ],
            'individual' => [
                'name' => 'Individual',
                'doctor_limit' => 1,
                'staff_limit' => 3,
                'monthly_visit_limit' => null,
                'features' => ['realtime', 'management_dashboard'],
                'prices' => [1 => 149_000, 6 => 799_000, 12 => 1_399_000],
            ],
            'team' => [
                'name' => 'Team',
                'doctor_limit' => null,
                'staff_limit' => null,
                'monthly_visit_limit' => null,
                'features' => ['realtime', 'management_dashboard'],
                'prices' => [1 => 499_000, 6 => 2_699_000, 12 => 4_999_000],
            ],
        ];

        foreach ($plans as $code => $attributes) {
            $plan = SubscriptionPlan::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $attributes['name'],
                    'doctor_limit' => $attributes['doctor_limit'],
                    'staff_limit' => $attributes['staff_limit'],
                    'monthly_visit_limit' => $attributes['monthly_visit_limit'],
                    'features' => $attributes['features'],
                ],
            );

            foreach ($attributes['prices'] as $durationMonths => $amount) {
                $plan->prices()->updateOrCreate(
                    ['duration_months' => $durationMonths],
                    ['amount' => $amount],
                );
            }
        }
    }
}

<?php

namespace Database\Factories;

use App\Models\Income;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Income> */
class IncomeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'description' => 'Salary',
            'amount' => '100000.00',
            'currency' => 'RSD',
            'date' => '2026-10-07',
        ];
    }
}

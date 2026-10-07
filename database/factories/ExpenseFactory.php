<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Receiver;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Expense> */
class ExpenseFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'receiver_id' => Receiver::factory(),
            'user_id' => fn (array $attributes) => Receiver::query()->whereKey($attributes['receiver_id'])->firstOrFail()->user_id,
            'description' => fake()->sentence(3),
            'amount' => '1250.50',
            'currency' => 'RSD',
            'date' => '2026-10-07',
            'payment_method' => 'card',
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DevUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $user = User::firstOrCreate(
                ['email' => 'dev@pixel2go.com'],
                [
                    'name' => 'Developer',
                    'password' => Hash::make('admin123'),
                ],
            );

            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $date = '2026-10-07';
            $incomes = [
                ['name' => 'Plata', 'amount' => 536000],
                ['name' => 'Ostali prihodi', 'amount' => 29000],
            ];

            foreach ($incomes as $income) {
                $user->incomes()->whereDate('date', $date)->firstOrCreate([
                    'description' => $income['name'],
                    'amount' => $income['amount'],
                    'currency' => 'RSD',
                ], ['date' => $date]);
            }

            $expenses = [
                ['name' => 'Porezi na platu', 'amount' => 20000],
                ['name' => 'Akontacioni porez', 'amount' => 6000],
                ['name' => 'Knjigovodja', 'amount' => 8000],
                ['name' => 'Infostan', 'amount' => 12000],
                ['name' => 'Struja', 'amount' => 3200],
                ['name' => 'A1', 'amount' => 3000],
                ['name' => 'MTS internet', 'amount' => 4000],
                ['name' => 'Odrzavanje zgrade', 'amount' => 2400],
                ['name' => 'GSuit', 'amount' => 2400],
                ['name' => 'Youtube Premium', 'amount' => 1000],
                ['name' => 'ChatGPT', 'amount' => 11000],
                ['name' => 'Netflix', 'amount' => 1300],
                ['name' => 'Google Drive', 'amount' => 1050],
                ['name' => 'Hetzner', 'amount' => 2100],
                ['name' => 'Audiable', 'amount' => 1300],
                ['name' => 'Postmark', 'amount' => 1900],
                ['name' => 'Cigare', 'amount' => 1000],
                ['name' => 'Cigare', 'amount' => 5000],
                ['name' => 'Black', 'amount' => 2000],
                ['name' => 'Black', 'amount' => 1000],
                ['name' => 'Black', 'amount' => 1000],
                ['name' => 'KP Kredit', 'amount' => 3000],
                ['name' => 'Mama', 'amount' => 11000],
                ['name' => 'Zice', 'amount' => 4500],
                ['name' => 'Hrana', 'amount' => 2000],
                ['name' => 'Akels kal', 'amount' => 300],
            ];

            $occurrences = [];

            foreach ($expenses as $expense) {
                $receiver = $user->receivers()->firstOrCreate(['name' => $expense['name']]);
                $key = $expense['name'].'|'.$expense['amount'];
                $occurrences[$key] = ($occurrences[$key] ?? 0) + 1;
                $attributes = [
                    'description' => $expense['name'],
                    'amount' => $expense['amount'],
                    'currency' => 'RSD',
                    'payment_method' => 'unspecified',
                ];

                $existingCount = $user->expenses()->where($attributes)
                    ->whereDate('date', $date)->where('receiver_id', $receiver->id)->count();

                if ($existingCount < $occurrences[$key]) {
                    $record = $user->expenses()->make([...$attributes, 'date' => $date]);
                    $record->receiver()->associate($receiver);
                    $record->save();
                }
            }
        });
    }
}

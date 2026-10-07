<?php

use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Database\Seeders\DevUserSeeder;
use Illuminate\Support\Facades\Hash;

test('the development seeder imports the financial snapshot with titles as receivers', function () {
    $this->seed(DevUserSeeder::class);
    $user = User::where('email', 'dev@pixel2go.com')->firstOrFail();

    expect(Hash::check('admin123', $user->password))->toBeTrue();
    expect($user->incomes()->count())->toBe(2);
    expect($user->expenses()->count())->toBe(26);
    expect((int) $user->incomes()->sum('amount'))->toBe(565000);
    expect((int) $user->expenses()->sum('amount'))->toBe(111450);
    expect($user->expenses()->where('description', 'Black')->where('amount', 1000)->count())->toBe(2);
    expect($user->expenses()->where('description', 'Cigare')->count())->toBe(2);

    foreach ($user->expenses()->with('receiver')->get() as $expense) {
        expect($expense->receiver->name)->toBe($expense->description);
        expect($expense->receiver->user_id)->toBe($user->id);
        expect($expense->date->format('Y-m-d'))->toBe('2026-10-07');
        expect($expense->currency)->toBe('RSD');
        expect($expense->payment_method)->toBe('unspecified');
    }
})->group('database');

test('rerunning the seeder preserves duplicate snapshot entries without adding new copies', function () {
    $this->seed(DevUserSeeder::class);
    $this->travelTo(now()->addDay());
    $this->seed(DevUserSeeder::class);

    $this->assertDatabaseCount('incomes', 2);
    $this->assertDatabaseCount('expenses', 26);
    $this->assertDatabaseCount('receivers', 23);
    expect(Expense::where('description', 'Black')->where('amount', 1000)->count())->toBe(2);
    expect(Income::whereDate('date', '2026-10-07')->count())->toBe(2);
})->group('database');

test('seeding preserves existing credentials and unrelated financial records', function () {
    $user = User::factory()->create(['email' => 'dev@pixel2go.com', 'name' => 'Existing user', 'password' => 'keep-this-password']);
    $income = Income::factory()->for($user)->create(['description' => 'Existing income']);
    $otherExpense = Expense::factory()->create();

    $this->seed(DevUserSeeder::class);

    expect(Hash::check('keep-this-password', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->name)->toBe('Existing user');
    $this->assertDatabaseHas('incomes', ['id' => $income->id, 'description' => 'Existing income']);
    $this->assertDatabaseHas('expenses', ['id' => $otherExpense->id, 'user_id' => $otherExpense->user_id]);
    expect($user->incomes()->count())->toBe(3);
    expect($user->expenses()->count())->toBe(26);
})->group('database');

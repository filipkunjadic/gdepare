<?php

use App\Models\Income;
use App\Models\Tag;
use App\Models\User;

function incomePayload(array $overrides = []): array
{
    return array_replace([
        'description' => 'Salary',
        'amount' => '100000.50',
        'currency' => 'RSD',
        'date' => '2026-10-07',
    ], $overrides);
}

test('guests cannot load or save income', function () {
    $this->getJson(route('incomes.index'))->assertUnauthorized();
    $this->postJson(route('incomes.store'), incomePayload())->assertUnauthorized();
})->group('no-database');

test('income requires amount currency and date', function () {
    $this->actingAs(User::factory()->make(['id' => 1]))
        ->postJson(route('incomes.store'), [])
        ->assertUnprocessable()->assertJsonValidationErrors(['amount', 'currency', 'date']);
})->group('no-database');

test('invalid income input is rejected', function (array $input, string $field) {
    $this->actingAs(User::factory()->make(['id' => 1]))
        ->postJson(route('incomes.store'), incomePayload($input))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'zero amount' => [['amount' => 0], 'amount'],
    'negative amount' => [['amount' => -1], 'amount'],
    'fractional cents' => [['amount' => '1.001'], 'amount'],
    'amount overflow' => [['amount' => '1000000000000'], 'amount'],
    'invalid currency' => [['currency' => 'invalid'], 'currency'],
    'invalid date' => [['date' => '2026-02-30'], 'date'],
    'long description' => [['description' => str_repeat('a', 256)], 'description'],
])->group('no-database');

test('income is saved for the current user regardless of a supplied owner', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('incomes.store'), incomePayload(['user_id' => $otherUser->id]))
        ->assertCreated()->assertJsonPath('amount', '100000.50')
        ->assertJsonPath('date', '2026-10-07')->assertJsonMissingPath('user_id');

    $this->assertDatabaseHas('incomes', [
        'id' => $response->json('id'), 'user_id' => $user->id,
        'amount' => '100000.50', 'description' => 'Salary', 'currency' => 'RSD',
    ]);
})->group('database');

test('income can be saved without a description', function () {
    $payload = incomePayload();
    unset($payload['description']);

    $response = $this->actingAs(User::factory()->create())->postJson(route('incomes.store'), $payload)
        ->assertCreated();

    $this->assertDatabaseHas('incomes', ['id' => $response->json('id'), 'description' => null]);
})->group('database');

test('income list excludes other users and sorts by date', function () {
    $user = User::factory()->create();
    $older = Income::factory()->for($user)->create(['date' => '2026-10-01']);
    $newer = Income::factory()->for($user)->create(['date' => '2026-10-07']);
    Income::factory()->create();

    $this->actingAs($user)->getJson(route('incomes.index'))->assertOk()
        ->assertJsonCount(2)->assertJsonPath('0.id', $newer->id)->assertJsonPath('1.id', $older->id);
})->group('database');

test('a new user has no income records', function () {
    $this->actingAs(User::factory()->create())->getJson(route('incomes.index'))
        ->assertOk()->assertExactJson([]);
})->group('database');

test('deleting a user removes their income and preserves other users income', function () {
    $user = User::factory()->create();
    $income = Income::factory()->for($user)->create();
    $otherIncome = Income::factory()->create();

    $user->delete();

    $this->assertDatabaseMissing('incomes', ['id' => $income->id]);
    $this->assertDatabaseHas('incomes', ['id' => $otherIncome->id]);
})->group('database');

test('editing income replaces its values without adding a second record or changing its owner', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $income = Income::factory()->for($user)->create();

    $this->actingAs($user)->patchJson(route('incomes.update', $income), incomePayload([
        'description' => null, 'amount' => '123.45', 'currency' => 'EUR', 'date' => '2026-10-06', 'user_id' => $otherUser->id,
    ]))->assertOk()->assertJsonPath('id', $income->id)->assertJsonPath('amount', '123.45')
        ->assertJsonPath('description', null)->assertJsonPath('currency', 'EUR')->assertJsonPath('date', '2026-10-06');

    $this->assertDatabaseCount('incomes', 1);
    $this->assertDatabaseHas('incomes', ['id' => $income->id, 'amount' => '123.45', 'user_id' => $user->id, 'currency' => 'EUR']);
})->group('database');

test('users cannot edit someone elses income or a missing income', function () {
    $income = Income::factory()->create(['amount' => '50.00']);
    $this->actingAs(User::factory()->create())->patchJson(route('incomes.update', $income), incomePayload())->assertNotFound();
    $this->patchJson(route('incomes.update', 999999), incomePayload())->assertNotFound();
    $this->assertDatabaseHas('incomes', ['id' => $income->id, 'amount' => '50.00']);
})->group('database');

test('invalid edits leave income unchanged', function () {
    $income = Income::factory()->create(['amount' => '50.00']);
    $this->actingAs($income->user)->patchJson(route('incomes.update', $income), incomePayload(['amount' => -1]))
        ->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->assertDatabaseHas('incomes', ['id' => $income->id, 'amount' => '50.00']);
})->group('database');

test('guests cannot update income', function () {
    $this->patchJson(route('incomes.update', 1), incomePayload())->assertUnauthorized();
})->group('no-database');

test('users can delete their own income without removing another record', function () {
    $record = Income::factory()->create();
    $other = Income::factory()->create();

    $this->actingAs($record->user)->deleteJson(route('incomes.destroy', $record))->assertNoContent();

    $this->assertDatabaseMissing('incomes', ['id' => $record->id]);
    $this->assertDatabaseHas('incomes', ['id' => $other->id]);
    $this->getJson(route('incomes.index'))->assertOk()->assertExactJson([]);
})->group('database');

test('users cannot delete another users income or a missing record', function () {
    $record = Income::factory()->create();
    $this->actingAs(User::factory()->create())->deleteJson(route('incomes.destroy', $record))->assertNotFound();
    $this->deleteJson(route('incomes.destroy', 999999))->assertNotFound();
    $this->assertDatabaseHas('incomes', ['id' => $record->id]);
})->group('database');

test('guests cannot delete incomes', function () {
    $this->deleteJson(route('incomes.destroy', 1))->assertUnauthorized();
})->group('no-database');

test('income shares existing user tags and never reuses another users tags', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create(['name' => 'Work']);
    $otherTag = Tag::factory()->create(['name' => 'Salary']);

    $response = $this->actingAs($user)->postJson(route('incomes.store'), incomePayload(['tags' => ['Work', 'Work', 'Salary']]))
        ->assertCreated()->assertJsonCount(2, 'tags');
    $incomeId = $response->json('id');
    $this->assertDatabaseHas('income_tag', ['income_id' => $incomeId, 'tag_id' => $tag->id, 'user_id' => $user->id]);
    $this->assertDatabaseMissing('income_tag', ['income_id' => $incomeId, 'tag_id' => $otherTag->id]);
    $this->getJson(route('incomes.index'))->assertOk()->assertJsonCount(2, '0.tags');

    $this->patchJson(route('incomes.update', $incomeId), incomePayload(['tags' => ['Salary']]))
        ->assertOk()->assertJsonCount(1, 'tags')->assertJsonPath('tags.0.name', 'Salary');
    $this->assertDatabaseMissing('income_tag', ['income_id' => $incomeId, 'tag_id' => $tag->id]);

    $this->patchJson(route('incomes.update', $incomeId), incomePayload())->assertOk()->assertJsonCount(1, 'tags');
    $this->patchJson(route('incomes.update', $incomeId), incomePayload(['tags' => []]))->assertOk()->assertJsonCount(0, 'tags');
    $this->assertDatabaseCount('income_tag', 0);
})->group('database');

test('deleting tagged income removes only its links and preserves shared tags', function () {
    $income = Income::factory()->create();
    $tag = Tag::factory()->for($income->user)->create();
    $income->tags()->attach($tag, ['user_id' => $income->user_id]);

    $this->actingAs($income->user)->deleteJson(route('incomes.destroy', $income))->assertNoContent();
    $this->assertDatabaseMissing('income_tag', ['income_id' => $income->id]);
    $this->assertDatabaseHas('tags', ['id' => $tag->id]);
})->group('database');

test('income rejects invalid tags', function (mixed $tags, string $field) {
    $this->actingAs(User::factory()->make(['id' => 1]))
        ->postJson(route('incomes.store'), incomePayload(['tags' => $tags]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'not an array' => ['Salary', 'tags'],
    'too many tags' => [array_fill(0, 21, 'Work'), 'tags'],
    'blank tag' => [['  '], 'tags.0'],
    'long tag' => [[str_repeat('a', 256)], 'tags.0'],
])->group('no-database');

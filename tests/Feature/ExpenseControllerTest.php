<?php

use App\Models\Expense;
use App\Models\Receiver;
use App\Models\Tag;
use App\Models\User;

function expensePayload(array $overrides = []): array
{
    return array_replace([
        'description' => 'Weekly groceries',
        'amount' => '1250.50',
        'currency' => 'RSD',
        'date' => '2026-10-07',
        'payment_method' => 'card',
        'receiver' => 'Local shop',
        'tags' => ['Food', 'Household'],
    ], $overrides);
}

test('guests cannot create expenses', function () {
    $this->postJson(route('expenses.store'), expensePayload())->assertUnauthorized();
})->group('no-database');

test('expense creation requires its mandatory fields', function () {
    $this->actingAs(User::factory()->make(['id' => 1]))
        ->postJson(route('expenses.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description', 'amount', 'currency', 'date', 'payment_method', 'receiver']);
})->group('no-database');

test('invalid expense input returns a validation error', function (array $input, string $field) {
    $this->actingAs(User::factory()->make(['id' => 1]))
        ->postJson(route('expenses.store'), expensePayload($input))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'zero amount' => [['amount' => 0], 'amount'],
    'negative amount' => [['amount' => -10], 'amount'],
    'too many decimals' => [['amount' => '1.234'], 'amount'],
    'amount overflow' => [['amount' => '1000000000000'], 'amount'],
    'invalid currency' => [['currency' => 'invalid'], 'currency'],
    'invalid date' => [['date' => '2026-02-30'], 'date'],
    'unknown payment method' => [['payment_method' => 'unknown'], 'payment_method'],
    'unknown category' => [['category' => 'income'], 'category'],
    'empty category' => [['category' => null], 'category'],
    'savings require RSD' => [['category' => 'savings', 'currency' => 'EUR'], 'currency'],
    'foreign amount requires currency' => [['category' => 'savings', 'foreign_amount' => '20.00'], 'foreign_currency'],
    'foreign currency requires amount' => [['category' => 'savings', 'foreign_currency' => 'EUR'], 'foreign_amount'],
    'foreign currency is EUR only' => [['category' => 'savings', 'foreign_amount' => '20.00', 'foreign_currency' => 'USD'], 'foreign_currency'],
    'foreign amount must be positive' => [['category' => 'savings', 'foreign_amount' => '-1.00', 'foreign_currency' => 'EUR'], 'foreign_amount'],
    'foreign amount precision' => [['category' => 'savings', 'foreign_amount' => '1.234', 'foreign_currency' => 'EUR'], 'foreign_amount'],
    'foreign amount overflow' => [['category' => 'savings', 'foreign_amount' => '1000000000000', 'foreign_currency' => 'EUR'], 'foreign_amount'],
    'expenses cannot have foreign amounts' => [['foreign_amount' => '20.00', 'foreign_currency' => 'EUR'], 'foreign_amount'],
    'blank receiver' => [['receiver' => '  '], 'receiver'],
    'long description' => [['description' => str_repeat('a', 256)], 'description'],
    'long receiver' => [['receiver' => str_repeat('a', 256)], 'receiver'],
    'non-array tags' => [['tags' => 'Food'], 'tags'],
    'too many tags' => [['tags' => array_fill(0, 21, 'Food')], 'tags'],
    'empty tag' => [['tags' => ['  ']], 'tags.0'],
    'long tag' => [['tags' => [str_repeat('a', 256)]], 'tags.0'],
])->group('no-database');

test('saving an expense persists its receiver and tags under the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherReceiver = Receiver::factory()->for($otherUser)->create(['name' => 'Local shop']);
    $otherTag = Tag::factory()->for($otherUser)->create(['name' => 'Food']);

    $response = $this->actingAs($user)->postJson(route('expenses.store'), expensePayload([
        'user_id' => $otherUser->id,
        'receiver_id' => $otherReceiver->id,
        'tag_ids' => [$otherTag->id],
    ]))->assertCreated()
        ->assertJsonPath('description', 'Weekly groceries')
        ->assertJsonPath('amount', '1250.50')
        ->assertJsonPath('date', '2026-10-07')
        ->assertJsonPath('receiver.name', 'Local shop')
        ->assertJsonCount(2, 'tags')
        ->assertJsonMissingPath('user_id');

    $this->assertDatabaseHas('expenses', [
        'id' => $response->json('id'), 'user_id' => $user->id, 'amount' => '1250.50',
        'receiver_id' => $response->json('receiver.id'),
    ]);
    $this->assertDatabaseHas('receivers', ['id' => $response->json('receiver.id'), 'user_id' => $user->id]);
    $this->assertDatabaseHas('tags', ['id' => $response->json('tags.0.id'), 'user_id' => $user->id]);
    $this->assertDatabaseHas('expense_tag', [
        'expense_id' => $response->json('id'), 'tag_id' => $response->json('tags.0.id'), 'user_id' => $user->id,
    ]);
})->group('database');

test('savings can be created listed edited and deleted by their owner', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson(route('expenses.store'), expensePayload([
        'category' => 'savings', 'description' => 'Emergency fund', 'receiver' => 'Savings account',
    ]))->assertCreated()->assertJsonPath('category', 'savings');
    $id = $response->json('id');

    $this->assertDatabaseHas('expenses', ['id' => $id, 'user_id' => $user->id, 'category' => 'savings']);
    $this->getJson(route('expenses.index'))->assertJsonPath('0.category', 'savings');
    $this->patchJson(route('expenses.update', $id), expensePayload(['amount' => '500.00']))
        ->assertOk()->assertJsonPath('category', 'savings')->assertJsonPath('amount', '500.00');
    $this->assertDatabaseHas('expenses', ['id' => $id, 'category' => 'savings', 'amount' => '500.00']);
    $this->deleteJson(route('expenses.destroy', $id))->assertNoContent();
    $this->assertDatabaseMissing('expenses', ['id' => $id]);
})->group('database');

test('an existing expense can be reclassified as savings and back without duplication', function () {
    $record = Expense::factory()->create();

    $this->actingAs($record->user)->patchJson(route('expenses.update', $record), expensePayload(['category' => 'savings']))
        ->assertOk()->assertJsonPath('category', 'savings');
    $this->assertDatabaseHas('expenses', ['id' => $record->id, 'category' => 'savings']);
    $this->patchJson(route('expenses.update', $record), expensePayload(['category' => 'expense']))
        ->assertOk()->assertJsonPath('category', 'expense');
    $this->assertDatabaseHas('expenses', ['id' => $record->id, 'category' => 'expense']);
    $this->assertDatabaseCount('expenses', 1);
})->group('database');

test('savings remain private to their owner', function () {
    $record = Expense::factory()->create(['category' => 'savings']);

    $this->actingAs(User::factory()->create())->getJson(route('expenses.index'))->assertExactJson([]);
    $this->patchJson(route('expenses.update', $record), expensePayload(['category' => 'expense']))->assertNotFound();
    $this->deleteJson(route('expenses.destroy', $record))->assertNotFound();
    $this->assertDatabaseHas('expenses', ['id' => $record->id, 'category' => 'savings']);
})->group('database');

test('savings retain both amounts and allow the optional euro amount to be edited or removed', function () {
    $user = User::factory()->create();
    $payload = expensePayload(['category' => 'savings', 'amount' => '320000.00', 'foreign_amount' => '2700.50', 'foreign_currency' => 'EUR']);

    $response = $this->actingAs($user)->postJson(route('expenses.store'), $payload)
        ->assertCreated()->assertJsonPath('amount', '320000.00')->assertJsonPath('currency', 'RSD')
        ->assertJsonPath('foreign_amount', '2700.50')->assertJsonPath('foreign_currency', 'EUR');
    $id = $response->json('id');
    $this->assertDatabaseHas('expenses', ['id' => $id, 'amount' => '320000.00', 'foreign_amount' => '2700.50', 'foreign_currency' => 'EUR']);
    $this->getJson(route('expenses.index'))->assertJsonPath('0.foreign_amount', '2700.50');

    $this->patchJson(route('expenses.update', $id), expensePayload(['amount' => '320000.00']))
        ->assertOk()->assertJsonPath('foreign_amount', '2700.50');
    $this->patchJson(route('expenses.update', $id), array_replace($payload, ['foreign_amount' => '2710.00']))
        ->assertOk()->assertJsonPath('foreign_amount', '2710.00');
    $this->assertDatabaseHas('expenses', ['id' => $id, 'foreign_amount' => '2710.00']);

    $this->patchJson(route('expenses.update', $id), array_replace($payload, ['foreign_amount' => null, 'foreign_currency' => null]))
        ->assertOk()->assertJsonPath('foreign_amount', null)->assertJsonPath('foreign_currency', null);
    $this->assertDatabaseHas('expenses', ['id' => $id, 'amount' => '320000.00', 'foreign_amount' => null, 'foreign_currency' => null]);
})->group('database');

test('reclassifying savings as an expense clears the secondary currency', function () {
    $record = Expense::factory()->create(['category' => 'savings', 'foreign_amount' => '20.00', 'foreign_currency' => 'EUR']);

    $this->actingAs($record->user)->patchJson(route('expenses.update', $record), expensePayload(['category' => 'expense']))
        ->assertOk()->assertJsonPath('foreign_amount', null)->assertJsonPath('foreign_currency', null);
    $this->assertDatabaseHas('expenses', ['id' => $record->id, 'category' => 'expense', 'foreign_amount' => null, 'foreign_currency' => null]);
})->group('database');

test('saving another expense reuses the users receiver and deduplicates tags', function () {
    $user = User::factory()->create();
    $receiver = Receiver::factory()->for($user)->create(['name' => 'Local shop']);
    $tag = Tag::factory()->for($user)->create(['name' => 'Food']);

    $response = $this->actingAs($user)->postJson(route('expenses.store'), expensePayload(['tags' => ['Food', 'Food']]))
        ->assertCreated()->assertJsonPath('receiver.id', $receiver->id)
        ->assertJsonCount(1, 'tags')->assertJsonPath('tags.0.id', $tag->id);

    $this->assertDatabaseCount('receivers', 1);
    $this->assertDatabaseCount('tags', 1);
    $this->assertDatabaseHas('expense_tag', ['expense_id' => $response->json('id'), 'tag_id' => $tag->id]);
})->group('database');

test('expenses can be saved without tags', function () {
    $user = User::factory()->create();
    $payload = expensePayload();
    unset($payload['tags']);

    $this->actingAs($user)->postJson(route('expenses.store'), $payload)
        ->assertCreated()->assertJsonCount(0, 'tags');
    $this->assertDatabaseCount('expenses', 1);
    $this->assertDatabaseCount('expense_tag', 0);
})->group('database');

test('the expense list contains only the current users records in date order', function () {
    $user = User::factory()->create();
    $receiver = Receiver::factory()->for($user)->create();
    $older = Expense::factory()->for($receiver)->create(['date' => '2026-10-01']);
    $newer = Expense::factory()->for($receiver)->create(['date' => '2026-10-07']);
    Expense::factory()->create();

    $this->actingAs($user)->getJson(route('expenses.index'))
        ->assertOk()->assertJsonCount(2)
        ->assertJsonPath('0.id', $newer->id)
        ->assertJsonPath('1.id', $older->id)
        ->assertJsonPath('0.receiver.id', $receiver->id)
        ->assertJsonPath('0.tags', []);
})->group('database');

test('a user without expenses receives an empty list', function () {
    $this->actingAs(User::factory()->create())->getJson(route('expenses.index'))
        ->assertOk()->assertExactJson([]);
})->group('database');

test('editing an expense updates its fields receiver and tags without creating another expense', function () {
    $user = User::factory()->create();
    $receiver = Receiver::factory()->for($user)->create();
    $expense = Expense::factory()->for($receiver)->create();
    $oldTag = Tag::factory()->for($user)->create();
    $expense->tags()->attach($oldTag, ['user_id' => $user->id]);

    $this->actingAs($user)->patchJson(route('expenses.update', $expense), expensePayload([
        'description' => 'Updated expense', 'amount' => '42.75', 'receiver' => 'New receiver',
        'tags' => ['New tag'], 'payment_method' => 'unspecified',
    ]))->assertOk()->assertJsonPath('id', $expense->id)->assertJsonPath('amount', '42.75')
        ->assertJsonPath('receiver.name', 'New receiver')->assertJsonCount(1, 'tags')
        ->assertJsonPath('tags.0.name', 'New tag');

    $this->assertDatabaseCount('expenses', 1);
    $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'description' => 'Updated expense', 'amount' => '42.75']);
    $this->assertDatabaseMissing('expense_tag', ['expense_id' => $expense->id, 'tag_id' => $oldTag->id]);

    $this->patchJson(route('expenses.update', $expense), expensePayload(['tags' => []]))
        ->assertOk()->assertJsonCount(0, 'tags');
    $this->assertDatabaseCount('expense_tag', 0);
})->group('database');

test('users cannot edit someone elses expense or a missing expense', function () {
    $expense = Expense::factory()->create(['description' => 'Private expense']);
    $this->actingAs(User::factory()->create())
        ->patchJson(route('expenses.update', $expense), expensePayload())->assertNotFound();
    $this->patchJson(route('expenses.update', 999999), expensePayload())->assertNotFound();
    $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'description' => 'Private expense']);
})->group('database');

test('invalid edits leave an expense unchanged', function () {
    $expense = Expense::factory()->create(['amount' => '50.00']);
    $this->actingAs($expense->user)->patchJson(route('expenses.update', $expense), expensePayload(['amount' => -1]))
        ->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'amount' => '50.00']);
})->group('database');

test('guests cannot update expenses', function () {
    $this->patchJson(route('expenses.update', 1), expensePayload())->assertUnauthorized();
})->group('no-database');

test('users can delete their own expense without removing another record', function () {
    $record = Expense::factory()->create();
    $other = Expense::factory()->create();

    $this->actingAs($record->user)->deleteJson(route('expenses.destroy', $record))->assertNoContent();

    $this->assertDatabaseMissing('expenses', ['id' => $record->id]);
    $this->assertDatabaseHas('expenses', ['id' => $other->id]);
    $this->getJson(route('expenses.index'))->assertOk()->assertExactJson([]);
})->group('database');

test('users cannot delete another users expense or a missing record', function () {
    $record = Expense::factory()->create();
    $this->actingAs(User::factory()->create())->deleteJson(route('expenses.destroy', $record))->assertNotFound();
    $this->deleteJson(route('expenses.destroy', 999999))->assertNotFound();
    $this->assertDatabaseHas('expenses', ['id' => $record->id]);
})->group('database');

test('guests cannot delete expenses', function () {
    $this->deleteJson(route('expenses.destroy', 1))->assertUnauthorized();
})->group('no-database');

test('deleting an expense removes its tag links but preserves its receiver and tags', function () {
    $expense = Expense::factory()->create();
    $tag = Tag::factory()->for($expense->user)->create();
    $expense->tags()->attach($tag, ['user_id' => $expense->user_id]);

    $this->actingAs($expense->user)->deleteJson(route('expenses.destroy', $expense))->assertNoContent();

    $this->assertDatabaseMissing('expense_tag', ['expense_id' => $expense->id]);
    $this->assertDatabaseHas('tags', ['id' => $tag->id]);
    $this->assertDatabaseHas('receivers', ['id' => $expense->receiver_id]);
})->group('database');

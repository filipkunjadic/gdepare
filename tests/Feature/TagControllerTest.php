<?php

use App\Models\Expense;
use App\Models\Income;
use App\Models\Tag;
use App\Models\User;

test('guests cannot open or manage tags', function () {
    $this->get(route('tags'))->assertRedirect(route('login'));
    $this->getJson(route('tags.index'))->assertUnauthorized();
    $this->postJson(route('tags.store'), ['name' => 'Food'])->assertUnauthorized();
    $this->patchJson(route('tags.update', 1), ['name' => 'Food'])->assertUnauthorized();
    $this->deleteJson(route('tags.destroy', 1))->assertUnauthorized();
})->group('no-database');

test('authenticated users can open the tags page directly', function () {
    $this->actingAs(User::factory()->make(['id' => 1]))->get(route('tags'))->assertOk()->assertViewIs('index');
})->group('no-database');

test('tags list includes unused tags and only the current users tags in name order', function () {
    $user = User::factory()->create();
    $last = Tag::factory()->for($user)->create(['name' => 'Work']);
    $first = Tag::factory()->for($user)->create(['name' => 'Food']);
    Tag::factory()->create(['name' => 'Private']);

    $this->actingAs($user)->getJson(route('tags.index'))->assertExactJson([
        ['id' => $first->id, 'name' => 'Food', 'icon' => null, 'color' => null],
        ['id' => $last->id, 'name' => 'Work', 'icon' => null, 'color' => null],
    ]);
})->group('database');

test('creating a tag scopes ownership and allows names used by other users', function () {
    $otherTag = Tag::factory()->create(['name' => 'Food']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('tags.store'), ['name' => '  Food  ', 'user_id' => $otherTag->user_id])
        ->assertCreated()->assertJsonPath('name', 'Food')->assertJsonMissingPath('user_id');

    $this->assertDatabaseHas('tags', ['id' => $response->json('id'), 'user_id' => $user->id, 'name' => 'Food']);
    $this->postJson(route('tags.store'), ['name' => 'Food'])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseCount('tags', 2);
})->group('database');

test('tag name must be nonblank text no longer than 255 characters', function (mixed $name) {
    $this->actingAs(User::factory()->create())->postJson(route('tags.store'), ['name' => $name])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseCount('tags', 0);
})->with([null, '   ', str_repeat('x', 256), 123])->group('database');

test('renaming a tag preserves its identity and updates expense and income labels', function () {
    $expense = Expense::factory()->create();
    $user = $expense->user;
    $income = Income::factory()->for($user)->create();
    $tag = Tag::factory()->for($user)->create(['name' => 'Old name']);
    $expense->tags()->attach($tag, ['user_id' => $user->id]);
    $income->tags()->attach($tag, ['user_id' => $user->id]);

    $this->actingAs($user)->patchJson(route('tags.update', $tag), ['name' => 'New name'])
        ->assertOk()->assertJsonPath('id', $tag->id)->assertJsonPath('name', 'New name');
    $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'New name']);
    $this->getJson(route('expenses.index'))->assertJsonPath('0.tags.0.name', 'New name');
    $this->getJson(route('incomes.index'))->assertJsonPath('0.tags.0.name', 'New name');
    $this->patchJson(route('tags.update', $tag), ['name' => 'New name'])->assertOk();
    $this->assertDatabaseCount('tags', 1);
})->group('database');

test('duplicate and invalid renames leave tags unchanged', function () {
    $tag = Tag::factory()->create(['name' => 'Original']);
    Tag::factory()->for($tag->user)->create(['name' => 'Existing']);

    $this->actingAs($tag->user)->patchJson(route('tags.update', $tag), ['name' => 'Existing'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->patchJson(route('tags.update', $tag), ['name' => '  '])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'Original']);
})->group('database');

test('users cannot change or delete another users tag or a missing tag', function () {
    $tag = Tag::factory()->create(['name' => 'Private']);
    $this->actingAs(User::factory()->create());

    foreach ([$tag->id, 999999] as $id) {
        $this->patchJson(route('tags.update', $id), ['name' => 'Changed'])->assertNotFound();
        $this->deleteJson(route('tags.destroy', $id))->assertNotFound();
    }
    $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'Private']);
})->group('database');

test('deleting a tag removes both types of links while preserving transactions and other tags', function () {
    $expense = Expense::factory()->create();
    $user = $expense->user;
    $income = Income::factory()->for($user)->create();
    $tag = Tag::factory()->for($user)->create();
    $otherTag = Tag::factory()->for($user)->create();
    $expense->tags()->attach([$tag->id, $otherTag->id], ['user_id' => $user->id]);
    $income->tags()->attach($tag, ['user_id' => $user->id]);

    $this->actingAs($user)->deleteJson(route('tags.destroy', $tag))->assertNoContent();

    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    $this->assertDatabaseMissing('expense_tag', ['tag_id' => $tag->id]);
    $this->assertDatabaseMissing('income_tag', ['tag_id' => $tag->id]);
    $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    $this->assertDatabaseHas('incomes', ['id' => $income->id]);
    $this->assertDatabaseHas('expense_tag', ['expense_id' => $expense->id, 'tag_id' => $otherTag->id]);
    $this->getJson(route('tags.index'))->assertJsonCount(1)->assertJsonPath('0.id', $otherTag->id);
})->group('database');

test('tags save their appearance and expose it on expense and income records', function () {
    $expense = Expense::factory()->create();
    $user = $expense->user;
    $income = Income::factory()->for($user)->create();
    $response = $this->actingAs($user)->postJson(route('tags.store'), ['name' => 'Home', 'icon' => 'home', 'color' => '#12abEF'])
        ->assertCreated()->assertJsonPath('icon', 'home')->assertJsonPath('color', '#12abEF');
    $id = $response->json('id');
    $this->assertDatabaseHas('tags', ['id' => $id, 'icon' => 'home', 'color' => '#12abEF', 'user_id' => $user->id]);
    $expense->tags()->attach($id, ['user_id' => $user->id]);
    $income->tags()->attach($id, ['user_id' => $user->id]);

    $this->patchJson(route('tags.update', $id), ['name' => 'Household', 'icon' => 'bills', 'color' => '#dc2626'])
        ->assertOk()->assertJsonPath('icon', 'bills')->assertJsonPath('color', '#dc2626');
    $this->getJson(route('expenses.index'))->assertJsonPath('0.tags.0.icon', 'bills')->assertJsonPath('0.tags.0.color', '#dc2626');
    $this->getJson(route('incomes.index'))->assertJsonPath('0.tags.0.icon', 'bills')->assertJsonPath('0.tags.0.color', '#dc2626');
    $this->getJson(route('tags.index'))->assertJsonPath('0.icon', 'bills');

    $this->patchJson(route('tags.update', $id), ['name' => 'Renamed'])->assertOk()->assertJsonPath('icon', 'bills')->assertJsonPath('color', '#dc2626');
    $this->patchJson(route('tags.update', $id), ['name' => 'Renamed', 'icon' => null])->assertOk()->assertJsonPath('icon', null);
    $this->assertDatabaseHas('tags', ['id' => $id, 'icon' => null, 'color' => '#dc2626']);
})->group('database');

test('tag appearance rejects unknown icons and invalid colors', function (array $input, string $field) {
    $tag = Tag::factory()->create(['icon' => 'home', 'color' => '#6366f1']);

    $this->actingAs($tag->user)->patchJson(route('tags.update', $tag), array_merge(['name' => $tag->name], $input))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseHas('tags', ['id' => $tag->id, 'icon' => 'home', 'color' => '#6366f1']);
})->with([
    [['icon' => '<svg onload="alert(1)">'], 'icon'],
    [['icon' => 'not-an-icon'], 'icon'],
    [['color' => 'red'], 'color'],
    [['color' => '#fff'], 'color'],
    [['color' => '#12345678'], 'color'],
])->group('database');

test('tag color can be cleared and new tags have no selected color', function () {
    $tag = Tag::factory()->create(['color' => '#dc2626']);

    $this->actingAs($tag->user)->patchJson(route('tags.update', $tag), ['name' => $tag->name, 'color' => null])
        ->assertOk()->assertJsonPath('color', null);
    $this->assertDatabaseHas('tags', ['id' => $tag->id, 'color' => null]);
    $this->postJson(route('tags.store'), ['name' => 'Default appearance'])
        ->assertCreated()->assertJsonPath('color', null)->assertJsonPath('icon', null);
});

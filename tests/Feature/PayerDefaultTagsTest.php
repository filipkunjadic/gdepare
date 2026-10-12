<?php

use App\Models\Expense;
use App\Models\Receiver;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('payer defaults reuse owned tags and expose their current badge appearance', function () {
    $payer = Receiver::factory()->create();
    $tag = Tag::factory()->for($payer->user)->create(['name' => 'Food', 'icon' => 'food', 'color' => '#dc2626']);
    $otherTag = Tag::factory()->create(['name' => 'Household']);

    $response = $this->actingAs($payer->user)->post(route('payers.update', $payer), [
        '_method' => 'PATCH',
        'name' => $payer->name,
        'default_tags' => [' Food ', 'Household', 'Food'],
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(2, 'default_tags')
        ->assertJsonPath('default_tags.0', ['id' => $tag->id, 'name' => 'Food', 'icon' => 'food', 'color' => '#dc2626'])
        ->assertJsonPath('default_tags.1.name', 'Household');

    $householdId = $response->json('default_tags.1.id');
    $this->assertDatabaseHas('receiver_tag', ['receiver_id' => $payer->id, 'tag_id' => $tag->id, 'user_id' => $payer->user_id]);
    $this->assertDatabaseHas('receiver_tag', ['receiver_id' => $payer->id, 'tag_id' => $householdId, 'user_id' => $payer->user_id]);
    $this->assertDatabaseMissing('receiver_tag', ['tag_id' => $otherTag->id]);
    $this->assertDatabaseHas('tags', ['id' => $householdId, 'name' => 'Household', 'user_id' => $payer->user_id]);
    $this->assertDatabaseCount('tags', 3);
    $this->getJson(route('payers.index'))->assertJsonPath('0.default_tags', $response->json('default_tags'));
})->group('database');

test('updating and clearing payer defaults preserves existing transactions and shared tags', function () {
    $expense = Expense::factory()->create();
    $payer = $expense->receiver;
    $tag = Tag::factory()->for($expense->user)->create(['name' => 'Original']);
    $expense->tags()->attach($tag, ['user_id' => $expense->user_id]);
    $payer->defaultTags()->attach($tag, ['user_id' => $payer->user_id]);

    $this->actingAs($expense->user)->patchJson(route('payers.update', $payer), ['name' => $payer->name, 'default_tags' => ['New default']])
        ->assertOk()->assertJsonCount(1, 'default_tags')->assertJsonPath('default_tags.0.name', 'New default');

    $this->assertDatabaseMissing('receiver_tag', ['receiver_id' => $payer->id, 'tag_id' => $tag->id]);
    $this->assertDatabaseHas('expense_tag', ['expense_id' => $expense->id, 'tag_id' => $tag->id]);
    $this->getJson(route('expenses.index'))->assertJsonCount(1, '0.tags')->assertJsonPath('0.tags.0.name', 'Original');

    $this->post(route('payers.update', $payer), ['_method' => 'PATCH', 'name' => $payer->name, 'default_tags' => ''], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('default_tags', []);

    $this->assertDatabaseMissing('receiver_tag', ['receiver_id' => $payer->id]);
    $this->assertDatabaseCount('tags', 2);
    $this->assertDatabaseHas('expense_tag', ['expense_id' => $expense->id, 'tag_id' => $tag->id]);
})->group('database');

test('omitting defaults preserves them and an explicit empty array clears them', function () {
    $payer = Receiver::factory()->create();
    $tag = Tag::factory()->for($payer->user)->create();
    $payer->defaultTags()->attach($tag, ['user_id' => $payer->user_id]);

    $this->actingAs($payer->user)->patchJson(route('payers.update', $payer), ['name' => 'Renamed'])
        ->assertOk()->assertJsonPath('default_tags.0.id', $tag->id);
    $this->assertDatabaseHas('receiver_tag', ['receiver_id' => $payer->id, 'tag_id' => $tag->id]);

    $this->patchJson(route('payers.update', $payer), ['name' => 'Renamed', 'default_tags' => []])
        ->assertOk()->assertJsonPath('default_tags', []);
    $this->assertDatabaseMissing('receiver_tag', ['receiver_id' => $payer->id]);
    $this->assertModelExists($tag);
})->group('database');

test('renamed tags stay linked to payers and deleting a tag removes it from defaults', function () {
    $payer = Receiver::factory()->create();
    $tag = Tag::factory()->for($payer->user)->create(['name' => 'Food']);
    $payer->defaultTags()->attach($tag, ['user_id' => $payer->user_id]);

    $this->actingAs($payer->user)->patchJson(route('tags.update', $tag), ['name' => 'Groceries'])
        ->assertOk()->assertJsonPath('name', 'Groceries');
    $this->getJson(route('payers.index'))->assertJsonPath('0.default_tags.0.name', 'Groceries');
    $this->assertDatabaseHas('receiver_tag', ['receiver_id' => $payer->id, 'tag_id' => $tag->id]);

    $this->deleteJson(route('tags.destroy', $tag))->assertNoContent();

    $this->assertDatabaseMissing('receiver_tag', ['receiver_id' => $payer->id]);
    $this->getJson(route('payers.index'))->assertJsonPath('0.default_tags', []);
    $this->assertModelExists($payer);
})->group('database');

test('other users receive 404 when changing payer defaults and cannot read them', function () {
    $payer = Receiver::factory()->create();
    $tag = Tag::factory()->for($payer->user)->create();
    $payer->defaultTags()->attach($tag, ['user_id' => $payer->user_id]);

    $this->actingAs(User::factory()->create())->patchJson(route('payers.update', $payer), ['name' => $payer->name, 'default_tags' => ['Injected']])
        ->assertNotFound();

    $this->getJson(route('payers.index'))->assertExactJson([]);
    $this->assertDatabaseHas('receiver_tag', ['receiver_id' => $payer->id, 'tag_id' => $tag->id]);
    $this->assertDatabaseCount('receiver_tag', 1);
    $this->assertDatabaseMissing('tags', ['name' => 'Injected']);
})->group('database');

test('invalid default tags return 422 and keep the payer and defaults unchanged', function (mixed $tags, string $field, string $message) {
    $payer = Receiver::factory()->create(['name' => 'Original']);
    $tag = Tag::factory()->for($payer->user)->create();
    $payer->defaultTags()->attach($tag, ['user_id' => $payer->user_id]);

    $this->actingAs($payer->user)->patchJson(route('payers.update', $payer), ['name' => 'Changed', 'default_tags' => $tags])
        ->assertUnprocessable()->assertJsonValidationErrors($field)->assertJson(['errors' => [$field => [$message]]]);

    $this->assertDatabaseHas('receivers', ['id' => $payer->id, 'name' => 'Original']);
    $this->assertDatabaseHas('receiver_tag', ['receiver_id' => $payer->id, 'tag_id' => $tag->id]);
    $this->assertDatabaseCount('receiver_tag', 1);
    $this->assertDatabaseCount('tags', 1);
})->with([
    'not an array' => ['Food', 'default_tags', 'The default tags field must be an array.'],
    'too many' => [array_fill(0, 21, 'Food'), 'default_tags', 'The default tags field must not have more than 20 items.'],
    'blank tag' => [['   '], 'default_tags.0', 'The default_tags.0 field is required.'],
    'long tag' => [[str_repeat('a', 256)], 'default_tags.0', 'The default_tags.0 field must not be greater than 255 characters.'],
    'numeric tag' => [[123], 'default_tags.0', 'The default_tags.0 field must be a string.'],
])->group('database');

test('invalid default tags cannot remove the payer image', function () {
    Storage::fake('local');
    Storage::fake('public');
    $payer = Receiver::factory()->create();
    $media = $payer->addMedia(UploadedFile::fake()->image('payer.png'))->toMediaCollection('image');

    $this->actingAs($payer->user)->patchJson(route('payers.update', $payer), ['name' => $payer->name, 'default_tags' => [''], 'remove_image' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('default_tags.0');

    $this->assertModelExists($media);
    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot('avatar'));
})->group('database');

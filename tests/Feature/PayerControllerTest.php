<?php

use App\Models\Expense;
use App\Models\Receiver;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('guests cannot access the payers page or data', function () {
    $this->get(route('payers'))->assertRedirect(route('login'));
    $this->getJson(route('payers.index'))->assertUnauthorized();
})->group('no-database');

test('authenticated users can open the payers page directly', function () {
    $this->actingAs(User::factory()->make(['id' => 1]))
        ->get(route('payers'))->assertOk()->assertViewIs('index');
})->group('no-database');

test('payer list includes unused payers sorted by name and excludes other users', function () {
    $user = User::factory()->create();
    $last = Receiver::factory()->for($user)->create(['name' => 'Zebra']);
    $first = Receiver::factory()->for($user)->create(['name' => 'Alpha']);
    Receiver::factory()->create(['name' => 'Private payer']);

    $this->actingAs($user)->getJson(route('payers.index'))->assertExactJson([
        ['id' => $first->id, 'name' => 'Alpha', 'image_url' => null, 'default_tags' => [], 'transactions_count' => 0],
        ['id' => $last->id, 'name' => 'Zebra', 'image_url' => null, 'default_tags' => [], 'transactions_count' => 0],
    ]);
})->group('database');

test('users without payers receive an empty list', function () {
    $this->actingAs(User::factory()->create())->getJson(route('payers.index'))->assertExactJson([]);
})->group('database');

test('payer transaction counts include expenses and savings across all dates for each owned payer', function () {
    $payer = Receiver::factory()->create(['name' => 'Alpha']);
    $otherOwnedPayer = Receiver::factory()->for($payer->user)->create(['name' => 'Beta']);
    Expense::factory()->for($payer, 'receiver')->count(2)->sequence(
        ['date' => '2025-01-15', 'category' => 'expense'],
        ['date' => '2026-10-07', 'category' => 'expense'],
    )->create();
    Expense::factory()->for($payer, 'receiver')->create(['category' => 'savings']);
    Expense::factory()->for($otherOwnedPayer, 'receiver')->create();
    $privatePayer = Receiver::factory()->create(['name' => 'Alpha']);
    Expense::factory()->for($privatePayer, 'receiver')->count(4)->create();

    $this->actingAs($payer->user)->getJson(route('payers.index'))->assertOk()->assertJsonCount(2)
        ->assertJsonPath('0.id', $payer->id)->assertJsonPath('0.transactions_count', 3)
        ->assertJsonPath('1.id', $otherOwnedPayer->id)->assertJsonPath('1.transactions_count', 1);
})->group('database');

test('guests cannot create or delete payers', function () {
    $this->postJson(route('payers.store'), ['name' => 'New payer'])->assertUnauthorized();
    $this->deleteJson(route('payers.destroy', 1))->assertUnauthorized();
})->group('no-database');

test('users can create a payer without a transaction and cannot assign it to another user', function () {
    $user = User::factory()->create();
    $otherPayer = Receiver::factory()->create(['name' => 'New payer']);

    $response = $this->actingAs($user)->postJson(route('payers.store'), [
        'name' => '  New payer  ',
        'user_id' => $otherPayer->user_id,
    ])->assertCreated()->assertJsonPath('name', 'New payer')->assertJsonPath('image_url', null)->assertJsonPath('default_tags', [])
        ->assertJsonPath('transactions_count', 0);

    $this->assertDatabaseHas('receivers', ['id' => $response->json('id'), 'name' => 'New payer', 'user_id' => $user->id]);
    $this->assertDatabaseCount('receivers', 2);
    $this->assertDatabaseCount('expenses', 0);
    $this->getJson(route('payers.index'))->assertExactJson([$response->json()]);
})->group('database');

test('new payers can include an image and owned default tags through a multipart form', function () {
    Storage::fake('local');
    Storage::fake('public');
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create(['name' => 'Food']);
    $otherTag = Tag::factory()->create(['name' => 'Household']);

    $response = $this->actingAs($user)->post(route('payers.store'), [
        'name' => 'Grocery store',
        'image' => UploadedFile::fake()->image('payer.png', 600, 200),
        'remove_image' => '0',
        'default_tags' => [' Food ', 'Household', 'Food'],
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonCount(2, 'default_tags')
        ->assertJsonPath('default_tags.0.id', $tag->id)->assertJsonPath('default_tags.1.name', 'Household');

    $payer = Receiver::findOrFail($response->json('id'));
    $media = $payer->getFirstMedia('image');
    $response->assertJsonPath('image_url', $media->getUrl('avatar'));
    $this->assertDatabaseHas('receivers', ['id' => $payer->id, 'user_id' => $user->id, 'name' => 'Grocery store']);
    $this->assertDatabaseHas('receiver_tag', ['receiver_id' => $payer->id, 'tag_id' => $tag->id, 'user_id' => $user->id]);
    $this->assertDatabaseHas('tags', ['id' => $response->json('default_tags.1.id'), 'user_id' => $user->id, 'name' => 'Household']);
    $this->assertDatabaseMissing('receiver_tag', ['tag_id' => $otherTag->id]);
    $this->assertDatabaseCount('receiver_tag', 2);
    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot('avatar'));
    $dimensions = getimagesize($media->getPath('avatar'));
    expect([$dimensions[0], $dimensions[1], $dimensions['mime']])->toBe([200, 200, 'image/webp']);
})->group('database');

test('invalid payer creation does not save a payer or tags', function (array $data, string $field) {
    $this->actingAs(User::factory()->create())->postJson(route('payers.store'), $data)
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('receivers', 0);
    $this->assertDatabaseCount('tags', 0);
})->with([
    'missing name' => [['default_tags' => ['Food']], 'name'],
    'blank name' => [['name' => '   ', 'default_tags' => ['Food']], 'name'],
    'long name' => [['name' => str_repeat('x', 256)], 'name'],
    'numeric name' => [['name' => 123], 'name'],
    'invalid tags' => [['name' => 'New payer', 'default_tags' => ['Food', '']], 'default_tags.1'],
    'invalid image' => [['name' => 'New payer', 'image' => 'invalid', 'default_tags' => ['Food']], 'image'],
])->group('database');

test('creating a duplicate payer returns 422 without changing the existing payer', function () {
    $payer = Receiver::factory()->create(['name' => 'Existing']);

    $this->actingAs($payer->user)->postJson(route('payers.store'), ['name' => 'Existing', 'default_tags' => ['Food']])
        ->assertUnprocessable()->assertJsonValidationErrors('name');

    $this->assertDatabaseHas('receivers', ['id' => $payer->id, 'name' => 'Existing', 'user_id' => $payer->user_id]);
    $this->assertDatabaseCount('receivers', 1);
    $this->assertDatabaseCount('tags', 0);
})->group('database');

test('guests receive 401 when editing a payer', function () {
    $this->patchJson(route('payers.update', 1), ['name' => 'Updated'])->assertUnauthorized();
})->group('no-database');

test('renaming a payer preserves its identity and updates existing expense labels', function () {
    $expense = Expense::factory()->create();
    $payer = $expense->receiver;
    $otherPayer = Receiver::factory()->create(['name' => 'New name']);

    $this->actingAs($expense->user)->patchJson(route('payers.update', $payer), [
        'name' => '  New name  ',
        'user_id' => $otherPayer->user_id,
    ])->assertOk()->assertExactJson(['id' => $payer->id, 'name' => 'New name', 'image_url' => null, 'default_tags' => [], 'transactions_count' => 1]);

    $this->assertDatabaseHas('receivers', ['id' => $payer->id, 'name' => 'New name', 'user_id' => $expense->user_id]);
    $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'receiver_id' => $payer->id]);
    $this->getJson(route('expenses.index'))->assertJsonPath('0.receiver.name', 'New name');
    $this->patchJson(route('payers.update', $payer), ['name' => 'New name'])->assertOk();
    $this->assertDatabaseCount('receivers', 2);
})->group('database');

test('other users and missing payers return 404 when editing', function () {
    $payer = Receiver::factory()->create(['name' => 'Private']);
    $this->actingAs(User::factory()->create());

    foreach ([$payer->id, 999999] as $id) {
        $this->patchJson(route('payers.update', $id), ['name' => 'Changed'])->assertNotFound();
    }

    $this->assertDatabaseHas('receivers', ['id' => $payer->id, 'name' => 'Private']);
})->group('database');

test('invalid payer names return 422 without changing the payer', function (mixed $name, string $message) {
    $payer = Receiver::factory()->create(['name' => 'Original']);

    $this->actingAs($payer->user)->patchJson(route('payers.update', $payer), ['name' => $name])
        ->assertUnprocessable()->assertJsonValidationErrors('name')->assertJsonPath('errors.name.0', $message);

    $this->assertDatabaseHas('receivers', ['id' => $payer->id, 'name' => 'Original']);
})->with([
    'missing' => [null, 'The name field is required.'],
    'blank' => ['   ', 'The name field is required.'],
    'too long' => [str_repeat('x', 256), 'The name field must not be greater than 255 characters.'],
    'not text' => [123, 'The name field must be a string.'],
])->group('database');

test('duplicate payer names return 422 and preserve both the name and image', function () {
    Storage::fake('local');
    Storage::fake('public');
    $payer = Receiver::factory()->create(['name' => 'Original']);
    Receiver::factory()->for($payer->user)->create(['name' => 'Existing']);
    $media = $payer->addMedia(UploadedFile::fake()->image('payer.png'))->toMediaCollection('image');

    $this->actingAs($payer->user)->patchJson(route('payers.update', $payer), ['name' => 'Existing', 'remove_image' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('name')->assertJsonPath('errors.name.0', 'The name has already been taken.');

    $this->assertDatabaseHas('receivers', ['id' => $payer->id, 'name' => 'Original']);
    $this->assertModelExists($media);
    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot('avatar'));
})->group('database');

test('payers can save a name and replacement image together through a multipart form', function () {
    Storage::fake('local');
    Storage::fake('public');
    $payer = Receiver::factory()->create();
    $oldMedia = $payer->addMedia(UploadedFile::fake()->image('old.png'))->toMediaCollection('image');

    $response = $this->actingAs($payer->user)->post(route('payers.update', $payer), [
        '_method' => 'PATCH',
        'name' => 'Updated payer',
        'image' => UploadedFile::fake()->image('new.png', 600, 200),
        'remove_image' => '0',
    ], ['Accept' => 'application/json'])->assertOk();

    $media = $payer->refresh()->getFirstMedia('image');
    $response->assertExactJson(['id' => $payer->id, 'name' => 'Updated payer', 'image_url' => $media->getUrl('avatar'), 'default_tags' => [], 'transactions_count' => 0]);
    $this->assertDatabaseHas('receivers', ['id' => $payer->id, 'name' => 'Updated payer']);
    $this->assertModelMissing($oldMedia);
    Storage::disk('local')->assertMissing($oldMedia->getPathRelativeToRoot());
    Storage::disk('public')->assertMissing($oldMedia->getPathRelativeToRoot('avatar'));
    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot('avatar'));
    $dimensions = getimagesize($media->getPath('avatar'));
    expect([$dimensions[0], $dimensions[1], $dimensions['mime']])->toBe([200, 200, 'image/webp']);
})->group('database');

test('renaming a payer keeps its image unless removal is requested', function () {
    Storage::fake('local');
    Storage::fake('public');
    $payer = Receiver::factory()->create();
    $media = $payer->addMedia(UploadedFile::fake()->image('payer.png'))->toMediaCollection('image');

    $this->actingAs($payer->user)->patchJson(route('payers.update', $payer), ['name' => 'Updated payer', 'remove_image' => false])
        ->assertOk()->assertJsonPath('image_url', $media->getUrl('avatar'));

    $this->assertModelExists($media);
    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot('avatar'));

    $this->patchJson(route('payers.update', $payer), ['name' => 'Without image', 'remove_image' => true])
        ->assertOk()->assertExactJson(['id' => $payer->id, 'name' => 'Without image', 'image_url' => null, 'default_tags' => [], 'transactions_count' => 0]);

    $this->assertDatabaseHas('receivers', ['id' => $payer->id, 'name' => 'Without image']);
    $this->assertModelMissing($media);
    Storage::disk('local')->assertMissing($media->getPathRelativeToRoot());
    Storage::disk('public')->assertMissing($media->getPathRelativeToRoot('avatar'));
})->group('database');

test('invalid payer images return 422 without saving any form changes', function (Closure $upload, string $message) {
    Storage::fake('local');
    Storage::fake('public');
    $payer = Receiver::factory()->create(['name' => 'Original']);
    $media = $payer->addMedia(UploadedFile::fake()->image('payer.png'))->toMediaCollection('image');

    $this->actingAs($payer->user)->patchJson(route('payers.update', $payer), ['name' => 'Changed', 'image' => $upload()])
        ->assertUnprocessable()->assertJsonValidationErrors('image')->assertJsonPath('errors.image.0', $message);

    $this->assertDatabaseHas('receivers', ['id' => $payer->id, 'name' => 'Original']);
    $this->assertModelExists($media);
    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot('avatar'));
})->with([
    'empty upload' => [fn () => null, 'The image field is required.'],
    'not an image' => [fn () => UploadedFile::fake()->createWithContent('payer.png', 'Not an image.')->mimeType('text/plain'), 'The image field must be an image.'],
    'GIF' => [fn () => UploadedFile::fake()->image('payer.gif'), 'The image field must be a file of type: jpg, jpeg, png, webp.'],
    'over 2 MB' => [fn () => UploadedFile::fake()->image('payer.png')->size(2049), 'The image field must not be greater than 2048 kilobytes.'],
    'too wide' => [fn () => UploadedFile::fake()->image('payer.png', 4097, 10), 'The image field has invalid image dimensions.'],
])->group('database');

test('invalid image removal flags return 422 without changing the payer', function () {
    $payer = Receiver::factory()->create(['name' => 'Original']);

    $this->actingAs($payer->user)->patchJson(route('payers.update', $payer), ['name' => 'Changed', 'remove_image' => 'invalid'])
        ->assertUnprocessable()->assertJsonValidationErrors('remove_image');

    $this->assertDatabaseHas('receivers', ['id' => $payer->id, 'name' => 'Original']);
})->group('database');

test('deleting an unused payer removes its image and defaults but keeps shared tags', function () {
    Storage::fake('local');
    Storage::fake('public');
    $payer = Receiver::factory()->create();
    $tag = Tag::factory()->for($payer->user)->create();
    $otherPayer = Receiver::factory()->for($payer->user)->create();
    $payer->defaultTags()->attach($tag, ['user_id' => $payer->user_id]);
    $otherPayer->defaultTags()->attach($tag, ['user_id' => $payer->user_id]);
    $media = $payer->addMedia(UploadedFile::fake()->image('payer.png'))->toMediaCollection('image');

    $this->actingAs($payer->user)->deleteJson(route('payers.destroy', $payer))->assertNoContent();

    $this->assertModelMissing($payer);
    $this->assertModelMissing($media);
    $this->assertModelExists($tag);
    $this->assertModelExists($otherPayer);
    $this->assertDatabaseMissing('receiver_tag', ['receiver_id' => $payer->id]);
    $this->assertDatabaseHas('receiver_tag', ['receiver_id' => $otherPayer->id, 'tag_id' => $tag->id]);
    Storage::disk('local')->assertMissing($media->getPathRelativeToRoot());
    Storage::disk('public')->assertMissing($media->getPathRelativeToRoot('avatar'));
    $this->getJson(route('payers.index'))->assertJsonCount(1)->assertJsonPath('0.id', $otherPayer->id);
    $this->deleteJson(route('payers.destroy', $payer))->assertNotFound();
})->group('database');

test('other users and missing payers return 404 when deleting', function () {
    $payer = Receiver::factory()->create();
    $this->actingAs(User::factory()->create());

    foreach ([$payer->id, 999999] as $id) {
        $this->deleteJson(route('payers.destroy', $id))->assertNotFound();
    }

    $this->assertModelExists($payer);
})->group('database');

test('payers with transactions cannot be deleted and their data stays intact', function (string $category) {
    Storage::fake('local');
    Storage::fake('public');
    $expense = Expense::factory()->create(['category' => $category]);
    $payer = $expense->receiver;
    $tag = Tag::factory()->for($expense->user)->create();
    $payer->defaultTags()->attach($tag, ['user_id' => $payer->user_id]);
    $expense->tags()->attach($tag, ['user_id' => $expense->user_id]);
    $media = $payer->addMedia(UploadedFile::fake()->image('payer.png'))->toMediaCollection('image');

    $this->actingAs($payer->user)->deleteJson(route('payers.destroy', $payer))
        ->assertUnprocessable()->assertJsonValidationErrors('payer')
        ->assertJsonPath('errors.payer.0', 'This payer has existing transactions. Assign them to another payer before deleting it.');

    $this->assertModelExists($payer);
    $this->assertModelExists($expense);
    $this->assertModelExists($media);
    $this->assertDatabaseHas('receiver_tag', ['receiver_id' => $payer->id, 'tag_id' => $tag->id]);
    $this->assertDatabaseHas('expense_tag', ['expense_id' => $expense->id, 'tag_id' => $tag->id]);
    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot('avatar'));
})->with(['expense', 'savings'])->group('database');

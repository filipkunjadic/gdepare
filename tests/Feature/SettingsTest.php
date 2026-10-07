<?php

use App\Models\User;

test('settings require login', function () {
    $this->get(route('settings'))->assertRedirectToRoute('login');
    $this->patchJson(route('settings.update'), [])->assertUnauthorized();
})->group('no-database');

test('users can save only their own name and supported date format', function () {
    $user = User::factory()->create();
    $other = User::factory()->create(['name' => 'Other user']);

    $this->actingAs($user)->patchJson(route('settings.update'), [
        'name' => 'Updated name', 'date_format' => 'd/m/Y', 'id' => $other->id, 'email' => 'changed@example.com', 'password' => 'changed',
    ])->assertOk()->assertExactJson(['id' => $user->id, 'name' => 'Updated name', 'email' => $user->email, 'date_format' => 'd/m/Y']);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated name', 'date_format' => 'd/m/Y', 'email' => $user->email, 'password' => $user->password]);
    $this->assertDatabaseHas('users', ['id' => $other->id, 'name' => 'Other user', 'date_format' => 'd.m.Y']);
    $this->get(route('settings'))->assertOk()->assertSee('Updated name')->assertSee('d\/m\/Y', false);
})->group('database');

test('invalid settings do not change the profile', function (array $input, string $field) {
    $user = User::factory()->create(['name' => 'Original']);

    $this->actingAs($user)->patchJson(route('settings.update'), array_replace(['name' => 'New name', 'date_format' => 'Y-m-d'], $input))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Original', 'date_format' => 'd.m.Y']);
})->with([
    [['name' => '   '], 'name'],
    [['name' => str_repeat('a', 256)], 'name'],
    [['date_format' => 'invalid'], 'date_format'],
    [['date_format' => null], 'date_format'],
])->group('database');

test('login returns the saved date preference', function () {
    $user = User::factory()->create(['date_format' => 'm/d/Y']);

    $this->postJson(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertOk()->assertJsonPath('user.date_format', 'm/d/Y');
})->group('database');

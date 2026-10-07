<?php

use App\Models\Receiver;
use App\Models\User;

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
        ['id' => $first->id, 'name' => 'Alpha'],
        ['id' => $last->id, 'name' => 'Zebra'],
    ]);
})->group('database');

test('users without payers receive an empty list', function () {
    $this->actingAs(User::factory()->create())->getJson(route('payers.index'))->assertExactJson([]);
})->group('database');

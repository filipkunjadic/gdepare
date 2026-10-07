<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('guests can open the homepage and login shell', function (string $routeName) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertViewIs('index')
        ->assertSee('"user":null', false);
})->with(['index', 'login'])->group('no-database');

test('guests are redirected away from dashboard pages', function (string $path) {
    $this->get($path)->assertRedirectToRoute('login');
})->with(['/dashboard', '/page'])->group('no-database');

test('guests receive 401 when requesting expenses as JSON', function () {
    $this->getJson(route('expenses.index'))->assertUnauthorized();
})->group('no-database');

test('login requires an email and password', function () {
    $this->postJson(route('login.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);

    $this->assertGuest();
})->group('no-database');

test('login rejects malformed email addresses', function () {
    $this->postJson(route('login.store'), [
        'email' => 'not-an-email',
        'password' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->assertGuest();
})->group('no-database');

test('login throttles repeated attempts before checking credentials', function () {
    $key = 'login:dev@example.com|127.0.0.1';

    for ($attempt = 0; $attempt < 5; $attempt++) {
        RateLimiter::hit($key, 60);
    }

    $response = $this->postJson(route('login.store'), [
        'email' => 'dev@example.com',
        'password' => 'password',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
    expect($response->json('errors.email.0'))->toStartWith('Too many login attempts.');
    $this->assertGuest();
})->group('no-database');

test('authenticated users can open the dashboard without exposing password hashes', function () {
    $user = User::factory()->make(['id' => 1]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertViewIs('index')
        ->assertDontSee($user->password, false);
})->group('no-database');

test('authenticated users are redirected from login to the dashboard', function () {
    $user = User::factory()->make(['id' => 1]);

    $this->actingAs($user)->get(route('login'))->assertRedirectToRoute('dashboard');
})->group('no-database');

test('logout clears authentication and private session values', function () {
    $user = User::factory()->make(['id' => 1, 'remember_token' => null]);

    $this->actingAs($user)->withSession(['private-data' => 'example'])
        ->postJson(route('logout'))
        ->assertOk()
        ->assertJsonStructure(['csrfToken'])
        ->assertSessionMissing('private-data');

    $this->assertGuest();
    $this->get(route('dashboard'))->assertRedirectToRoute('login');
})->group('no-database');

test('signup and password recovery pages are not provided', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/register', '/forgot-password'])->group('no-database');

test('valid credentials start a session and return the user for Vue', function () {
    $user = User::factory()->create();

    $this->postJson(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonMissingPath('user.password')
        ->assertJsonStructure(['csrfToken']);

    $this->assertAuthenticatedAs($user);
})->group('database');

test('invalid credentials do not start a session or flash the password', function () {
    $user = User::factory()->create();

    $this->postJson(route('login.store'), [
        'email' => $user->email,
        'password' => 'incorrect-password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'The email or password is incorrect.'])
        ->assertSessionMissing('_old_input.password');

    $this->assertGuest();
})->group('database');

test('the transaction page requires login', function () {
    $this->get(route('transactions'))->assertRedirectToRoute('login');
})->group('no-database');

test('authenticated users can load the transaction page directly', function () {
    $this->actingAs(User::factory()->make(['id' => 1]))->get(route('transactions'))
        ->assertOk()->assertViewIs('index');
})->group('no-database');

<?php

use App\FinancialReport;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Receiver;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;

test('reports require login', function () {
    $this->get(route('reports'))->assertRedirect(route('login'));
    $this->getJson(route('reports.download', ['period' => 'month', 'year' => 2026, 'month' => 10]))->assertUnauthorized();
})->group('no-database');

test('reports page can be opened directly after login', function () {
    $this->actingAs(User::factory()->make(['id' => 1]))->get(route('reports'))->assertOk()->assertViewIs('index');
})->group('no-database');

test('invalid report periods are rejected', function (array $parameters, string $field) {
    $this->actingAs(User::factory()->make(['id' => 1]))->getJson(route('reports.download', $parameters))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['period' => 'day', 'year' => 2026], 'period'],
    [['period' => 'month', 'year' => 2026], 'month'],
    [['period' => 'month', 'year' => 2026, 'month' => 13], 'month'],
    [['period' => 'month', 'year' => 2026, 'month' => 0], 'month'],
    [['period' => 'year', 'year' => 9999], 'year'],
    [['period' => 'year', 'year' => 'invalid'], 'year'],
    [['period' => 'year'], 'year'],
])->group('no-database');

test('report data is scoped to the owner and period and deducts savings only once', function () {
    $user = User::factory()->create();
    $payer = Receiver::factory()->for($user)->create();
    Income::factory()->for($user)->create(['amount' => '1000.00', 'date' => '2026-10-01']);
    Income::factory()->for($user)->create(['amount' => '20.00', 'currency' => 'EUR', 'date' => '2026-10-31']);
    Expense::factory()->for($payer)->create(['amount' => '100.25', 'date' => '2026-10-31']);
    Expense::factory()->for($payer)->create(['amount' => '300.50', 'category' => 'savings', 'foreign_amount' => '2.55', 'foreign_currency' => 'EUR', 'date' => '2026-10-02']);
    Expense::factory()->for($payer)->create(['date' => '2026-09-30']);
    Expense::factory()->for($payer)->create(['date' => '2026-11-01']);
    Income::factory()->create(['description' => 'Other user income']);
    Expense::factory()->create(['description' => 'Other user expense']);

    $data = (new FinancialReport)->data($user, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-01'));

    expect($data['totals']['RSD'])->toBe(['income' => '1000.00', 'expenses' => '100.25', 'savings' => '300.50', 'balance' => '599.25', 'savings_eur' => '2.55']);
    expect($data['totals']['EUR']['balance'])->toBe('20.00');
    expect($data['transactions'])->toHaveCount(4);
    expect(array_column($data['transactions'], 'date'))->toBe(['2026-10-01', '2026-10-02', '2026-10-31', '2026-10-31']);
})->group('database');

test('yearly reports include both year boundaries and exclude the next year', function () {
    $user = User::factory()->create();
    Income::factory()->for($user)->create(['amount' => '10.00', 'date' => '2026-01-01']);
    Income::factory()->for($user)->create(['amount' => '20.00', 'date' => '2026-12-31']);
    Income::factory()->for($user)->create(['amount' => '99.00', 'date' => '2027-01-01']);

    $data = (new FinancialReport)->data($user, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2027-01-01'));
    expect($data['totals']['RSD']['income'])->toBe('30.00');
    expect($data['transactions'])->toHaveCount(2);
    $this->actingAs($user)->getJson(route('reports.download', ['period' => 'year', 'year' => 2026]))
        ->assertOk()->assertDownload('finance-report-2026.pdf')->assertHeader('Content-Type', 'application/pdf');
})->group('database');

test('empty periods generate a valid PDF download', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->getJson(route('reports.download', ['period' => 'month', 'year' => 2026, 'month' => 2]))
        ->assertOk()->assertDownload('finance-report-2026-02.pdf')->assertHeader('Content-Type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF-');
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
})->group('database');

test('monthly PDF paginates a full transaction list', function () {
    $user = User::factory()->create(['name' => 'Đorđe Petrović']);
    $payer = Receiver::factory()->for($user)->create(['name' => 'Računi i kućni troškovi']);
    Income::factory()->for($user)->create(['amount' => '536000.00', 'description' => 'Plata']);
    Expense::factory()->for($payer)->create(['amount' => '320000.00', 'category' => 'savings', 'description' => 'Štek', 'foreign_amount' => '2730.00', 'foreign_currency' => 'EUR']);
    Expense::factory()->for($payer)->count(35)->create(['description' => 'Mesečni troškovi za domaćinstvo', 'amount' => '1500.25']);
    Expense::factory()->for($payer)->create(['description' => str_repeat('Dugačak opis troška ', 12), 'amount' => '999999999999.99']);

    $response = $this->actingAs($user)->getJson(route('reports.download', ['period' => 'month', 'year' => 2026, 'month' => 10]))
        ->assertOk()->assertDownload('finance-report-2026-10.pdf');
    $pdf = $response->getContent();
    expect($pdf)->toStartWith('%PDF-')->toContain('%%EOF');
    expect(preg_match_all('~/Type\s*/Page\b~', $pdf))->toBeGreaterThan(1);
    if ($previewPath = getenv('REPORT_PREVIEW_PATH')) {
        file_put_contents($previewPath, $pdf);
    }
})->group('database');

test('report money formatting preserves exact large values', function () {
    expect(FinancialReport::formatAmount('99999999999998.99'))->toBe('99,999,999,999,998.99');
    expect(FinancialReport::formatAmount('-0.01'))->toBe('-0.01');
})->group('no-database');

test('report statistics use exact totals and separate overlapping tags, savings and currencies', function () {
    $user = User::factory()->create();
    $payer = Receiver::factory()->for($user)->create(['name' => 'Market']);
    $tags = Tag::factory()->for($user)->createMany([['name' => 'Food'], ['name' => 'Home']]);
    $income = Income::factory()->for($user)->create(['amount' => '1000.00']);
    $income->tags()->attach($tags[0], ['user_id' => $user->id]);
    $expense = Expense::factory()->for($payer)->create(['amount' => '100.01']);
    $expense->tags()->attach($tags->modelKeys(), ['user_id' => $user->id]);
    Expense::factory()->for($payer)->create(['amount' => '50.00']);
    Expense::factory()->for($payer)->create(['amount' => '300.00', 'category' => 'savings']);
    Expense::factory()->for($payer)->create(['amount' => '20.00', 'currency' => 'EUR']);
    Expense::factory()->for($payer)->create(['amount' => '900.00', 'date' => '2026-11-01']);
    Expense::factory()->create(['amount' => '800.00']);

    $data = (new FinancialReport)->data($user, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-01'));

    expect($data['statistics']['RSD'])->toMatchArray([
        'counts' => ['income' => 1, 'expense' => 2, 'savings' => 1],
        'average_expense' => '75.01', 'spending_rate' => '15.0', 'savings_rate' => '30.0',
    ]);
    expect($data['statistics']['RSD']['groups']['payers'])->toBe([['label' => 'Market', 'amount' => '150.01', 'percentage' => '100.0']]);
    expect($data['statistics']['RSD']['groups']['expense_tags'])->toBe([
        ['label' => 'Food', 'amount' => '100.01', 'percentage' => '66.7'],
        ['label' => 'Home', 'amount' => '100.01', 'percentage' => '66.7'],
        ['label' => 'Untagged', 'amount' => '50.00', 'percentage' => '33.3'],
    ]);
    expect($data['statistics']['RSD']['groups']['income_tags'])->toBe([['label' => 'Food', 'amount' => '1000.00', 'percentage' => '100.0']]);
    expect($data['statistics']['EUR']['spending_rate'])->toBeNull();
    expect($data['statistics']['EUR']['savings_rate'])->toBeNull();
    expect($data['statistics']['EUR']['average_expense'])->toBe('20.00');
    expect($data['statistics']['EUR']['groups']['income_tags'])->toBe([]);
})->group('database');

test('report rankings keep the five highest amounts without merging payers with equal names', function () {
    $user = User::factory()->create();
    foreach ([10, 20, 30, 40, 50, 60] as $amount) {
        $payer = Receiver::factory()->for($user)->create(['name' => 'Same name']);
        Expense::factory()->for($payer)->create(['amount' => $amount]);
    }

    $data = (new FinancialReport)->data($user, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-01'));

    expect(array_column($data['statistics']['RSD']['groups']['payers'], 'amount'))->toBe(['60.00', '50.00', '40.00', '30.00', '20.00']);
})->group('database');

test('PDF transaction dates use the selected display format', function (string $format, string $expected) {
    $html = view('report-pdf', [
        'period' => 'October 2026', 'owner' => 'Test user', 'generated' => $expected.' 12:00', 'dateFormat' => $format,
        'totals' => [], 'statistics' => [],
        'transactions' => [['date' => '2026-10-07', 'kind' => 'income', 'description' => 'Salary', 'payer' => '', 'tags' => '', 'amount' => '10.00', 'currency' => 'RSD', 'foreign_amount' => null, 'foreign_currency' => null]],
    ])->render();

    expect($html)->toContain('<td>'.$expected.'</td>');
})->with([
    ['d.m.Y', '07.10.2026'], ['d/m/Y', '07/10/2026'], ['m/d/Y', '10/07/2026'], ['Y-m-d', '2026-10-07'],
])->group('no-database');

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../../resources/js/utils/balances.js', import.meta.url), 'utf8');
const { calculateBalances, calculateTagTotals, calculatePayerTotals, calculateSpendingBreakdown, calculateSavingsDisplay, calculateTransactionGroups } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const record = (amount, currency = 'RSD') => ({ amount, currency });

test('savings widget sums entered euros while available balance deducts only RSD', () => {
    const savings = [
        { ...record('120.00'), category: 'savings', foreign_amount: '1.01', foreign_currency: 'EUR' },
        { ...record('240.00'), category: 'savings', foreign_amount: '2.02', foreign_currency: 'EUR' },
        { ...record('50.00'), category: 'savings', foreign_amount: null, foreign_currency: null },
        { ...record('10.00'), category: 'expense' },
    ];

    assert.deepEqual(calculateSavingsDisplay(savings, 'RSD'), { amount: '3.03', currency: 'EUR', missingCount: 1 });
    assert.equal(calculateBalances([record('500.00')], savings)[0].balance, '80.00');
    assert.deepEqual(calculateSavingsDisplay(savings, 'USD'), { amount: '0.00', currency: 'USD', missingCount: 0 });
});

test('savings widget falls back to the base amount when no EUR values have been entered', () => {
    assert.deepEqual(calculateSavingsDisplay([{ ...record('320000.00'), category: 'savings' }], 'RSD'), {
        amount: '320000.00', currency: 'RSD', missingCount: 0,
    });
    assert.deepEqual(calculateSavingsDisplay([], 'RSD'), { amount: '0.00', currency: 'RSD', missingCount: 0 });
});

test('savings reduce available balance exactly once without increasing expenses', () => {
    const result = calculateBalances([record('100.00')], [record('20.00'), { ...record('30.01'), category: 'savings' }]);

    assert.deepEqual(result, [{ currency: 'RSD', income: '100.00', expenses: '20.00', savings: '30.01', balance: '49.99', negative: false }]);
    assert.deepEqual(calculateBalances([], [{ ...record('1.25', 'EUR'), category: 'savings' }]), [
        { currency: 'EUR', income: '0.00', expenses: '0.00', savings: '1.25', balance: '-1.25', negative: true },
    ]);
});

test('savings are excluded from spending charts and tag and payer percentages', () => {
    const expenses = [
        { ...record('25.00'), receiver: { id: 1, name: 'Shop' }, tags: [{ id: 1 }] },
        { ...record('75.00'), receiver: { id: 2, name: 'Other' }, tags: [] },
        { ...record('900.00'), category: 'savings', receiver: { id: 1, name: 'Shop' }, tags: [{ id: 1 }] },
    ];

    assert.equal(calculateTagTotals(expenses, 1)[0].percentage, 25);
    assert.equal(calculatePayerTotals(expenses, 1)[0].percentage, 25);
    assert.deepEqual(calculateSpendingBreakdown(expenses, 'RSD'), [
        { id: 2, label: 'Other', amount: '75.00', percentage: 75 },
        { id: 1, label: 'Shop', amount: '25.00', percentage: 25 },
    ]);
});

test('income minus expenses is exact to the cent', () => {
    assert.deepEqual(calculateBalances([record('0.10'), record('0.20')], [record('0.29')]), [
        { currency: 'RSD', income: '0.30', expenses: '0.29', savings: '0.00', balance: '0.01', negative: false },
    ]);
});

test('different currencies remain separate and expense-only balances are negative', () => {
    assert.deepEqual(calculateBalances([record('200.00', 'EUR')], [record('5.25')]), [
        { currency: 'EUR', income: '200.00', expenses: '0.00', savings: '0.00', balance: '200.00', negative: false },
        { currency: 'RSD', income: '0.00', expenses: '5.25', savings: '0.00', balance: '-5.25', negative: true },
    ]);
});

test('large accumulated totals retain every cent', () => {
    const incomes = Array.from({ length: 100 }, () => record('999999999999.99'));
    assert.equal(calculateBalances(incomes, [record('0.01')])[0].balance, '99999999999998.99');
});

test('empty records have no currency totals', () => {
    assert.deepEqual(calculateBalances([], []), []);
});

test('whole amounts and a negative one-cent balance are handled', () => {
    assert.equal(calculateBalances([record('1')], [record('1.01')])[0].balance, '-0.01');
});


test('tag percentages include untagged expenses in the total and count multi-tag records once', () => {
    const expenses = [
        { ...record('10.00'), tags: [{ id: 1 }, { id: 2 }] },
        { ...record('20.00'), tags: [{ id: 1 }] },
        { ...record('70.00'), tags: [] },
    ];
    assert.deepEqual(calculateTagTotals(expenses, 1), [
        { currency: 'RSD', amount: '30.00', total: '100.00', percentage: 30 },
    ]);
    assert.equal(calculateTagTotals(expenses, 2)[0].percentage, 10);
});

test('tag percentages use totals in the same currency and round to two decimals', () => {
    assert.deepEqual(calculateTagTotals([
        { ...record('1.00', 'EUR'), tags: [{ id: 1 }] },
        { ...record('2.00', 'EUR'), tags: [] },
        { ...record('200.00'), tags: [{ id: 1 }] },
    ], 1), [
        { currency: 'EUR', amount: '1.00', total: '3.00', percentage: 33.33 },
        { currency: 'RSD', amount: '200.00', total: '200.00', percentage: 100 },
    ]);
});

test('missing tags and zero totals do not produce invalid percentages', () => {
    assert.deepEqual(calculateTagTotals([], 1), []);
    assert.deepEqual(calculateTagTotals([{ ...record('1.00'), tags: [] }], 1), []);
    assert.equal(calculateTagTotals([{ ...record('0.00'), tags: [{ id: 1 }] }], 1)[0].percentage, 0);
});


test('payer totals use receiver identity and the full expense total per currency', () => {
    const expenses = [
        { ...record('20.00'), receiver: { id: 1, name: 'Shop' } },
        { ...record('10.00'), receiver: { id: 1, name: 'Shop' } },
        { ...record('70.00'), receiver: { id: 2, name: 'Shop' } },
        { ...record('15.00', 'EUR'), receiver: { id: 1, name: 'Shop' } },
    ];
    assert.deepEqual(calculatePayerTotals(expenses, 1), [
        { currency: 'EUR', amount: '15.00', total: '15.00', percentage: 100 },
        { currency: 'RSD', amount: '30.00', total: '100.00', percentage: 30 },
    ]);
    assert.deepEqual(calculatePayerTotals(expenses, 3), []);
});


test('spending chart groups by payer, separates currencies and combines the remainder', () => {
    const expenses = Array.from({ length: 7 }, (_, index) => ({
        ...record('10.00'), receiver: { id: index + 1, name: `Payer ${index + 1}` },
    }));
    expenses.push({ ...record('30.00'), receiver: { id: 1, name: 'Payer 1' } });
    expenses.push({ ...record('1000.00', 'EUR'), receiver: { id: 8, name: 'Euro payer' } });
    const result = calculateSpendingBreakdown(expenses, 'RSD');
    assert.equal(result.length, 6);
    assert.deepEqual(result[0], { id: 1, label: 'Payer 1', amount: '40.00', percentage: 40 });
    assert.deepEqual(result[5], { id: null, label: 'Other payers', amount: '20.00', percentage: 20 });
    assert.equal(result.reduce((sum, item) => sum + item.percentage, 0), 100);
    assert.deepEqual(calculateSpendingBreakdown(expenses, 'USD'), []);
});


test('payer grid includes every payer sorted by amount without savings or mixed currencies', () => {
    const expenses = Array.from({ length: 8 }, (_, id) => ({ ...record('10.00'), receiver: { id, name: 'Same label' } }));
    expenses.push({ ...record('20.00'), receiver: { id: 0, name: 'Same label' } });
    expenses.push({ ...record('999.00'), category: 'savings', receiver: { id: 99, name: 'Savings' } });
    expenses.push({ ...record('5.00', 'EUR'), receiver: { id: 0, name: 'Same label' } });

    const groups = calculateTransactionGroups(expenses, 'expense');
    const rsd = groups.filter(group => group.currency === 'RSD');

    assert.equal(rsd.length, 8);
    assert.equal(rsd[0].amount, '30.00');
    assert.equal(rsd[0].percentage, 30);
    assert.equal(rsd[0].count, 2);
    assert.equal(groups.find(group => group.currency === 'EUR').percentage, 100);
    assert.equal(new Set(groups.map(group => group.key)).size, 9);
});

test('income grid groups repeated descriptions and handles missing descriptions', () => {
    const incomes = [
        { ...record('50.00'), description: 'Salary' },
        { ...record('25.00'), description: 'Salary' },
        { ...record('20.00'), description: null },
        { ...record('5.00'), description: '' },
    ];

    const groups = calculateTransactionGroups(incomes, 'income');

    assert.deepEqual(groups.map(({ label, amount, percentage }) => ({ label, amount, percentage })), [
        { label: 'Salary', amount: '75.00', percentage: 75 },
        { label: 'Unspecified income', amount: '25.00', percentage: 25 },
    ]);
    assert.deepEqual(calculateTransactionGroups([], 'income'), []);
});

test('tag grids use the full total including untagged records and count each tag once per record', () => {
    const records = [
        { ...record('25.00'), tags: [{ id: 1, name: 'Food' }, { id: 2, name: 'Home' }, { id: 1, name: 'Food' }] },
        { ...record('75.00'), tags: [] },
        { ...record('999.00'), category: 'savings', tags: [{ id: 1, name: 'Food' }] },
    ];

    for (const kind of ['expense', 'income']) {
        const groups = calculateTransactionGroups(records, kind, 'tag');
        assert.equal(groups.length, 2);
        assert.deepEqual(groups.map(group => [group.amount, group.percentage, group.count]), [['25.00', 25, 1], ['25.00', 25, 1]]);
    }
    assert.deepEqual(calculateTransactionGroups([{ ...record('10.00') }], 'income', 'tag'), []);
});


test('dashboard tag groups retain their icon and chosen color', () => {
    const groups = calculateTransactionGroups([
        { ...record('20.00'), tags: [{ id: 1, name: 'Home', icon: 'home', color: '#12abef' }] },
    ], 'expense', 'tag');

    assert.equal(groups[0].icon, 'home');
    assert.equal(groups[0].color, '#12abef');
    assert.equal(groups[0].amount, '20.00');
});

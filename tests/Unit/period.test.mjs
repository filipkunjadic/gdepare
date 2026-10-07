import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

async function sourceModule(name) {
    const source = await readFile(new URL(`../../resources/js/utils/${name}.js`, import.meta.url), 'utf8');
    return import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
}

const { recordsForMonth, shiftMonth, isValidMonth } = await sourceModule('period');
const { calculateBalances, calculateTagTotals } = await sourceModule('balances');
const { latestTransactions } = await sourceModule('transactions');

test('month filtering includes both boundaries and excludes other months and years', () => {
    const records = ['2026-09-30', '2026-10-01', '2026-10-31', '2026-11-01', '2025-10-01'].map(date => ({ date }));

    assert.deepEqual(recordsForMonth(records, '2026-10'), [{ date: '2026-10-01' }, { date: '2026-10-31' }]);
    assert.deepEqual(recordsForMonth(records, '2027-01'), []);
    assert.equal(records.length, 5);
});

test('month navigation crosses year boundaries in both directions', () => {
    assert.equal(shiftMonth('2026-12', 1), '2027-01');
    assert.equal(shiftMonth('2026-01', -1), '2025-12');
    assert.equal(shiftMonth('2026-03', -1), '2026-02');
    for (const month of ['', '2026-00', '2026-13', '0000-01', '10000-01', '2026-1']) {
        assert.equal(isValidMonth(month), false);
    }
    assert.equal(isValidMonth('2026-10'), true);
});

test('monthly balances and tag percentages exclude other months from totals', () => {
    const expenses = recordsForMonth([
        { date: '2026-10-01', amount: '25.00', currency: 'RSD', tags: [{ id: 1 }] },
        { date: '2026-10-31', amount: '75.00', currency: 'RSD', tags: [] },
        { date: '2026-09-01', amount: '900.00', currency: 'RSD', tags: [{ id: 1 }] },
    ], '2026-10');
    const incomes = recordsForMonth([
        { date: '2026-10-01', amount: '200.00', currency: 'RSD' },
        { date: '2026-11-01', amount: '500.00', currency: 'RSD' },
    ], '2026-10');

    assert.deepEqual(calculateBalances(incomes, expenses), [{ currency: 'RSD', income: '200.00', expenses: '100.00', savings: '0.00', balance: '100.00', negative: false }]);
    assert.equal(calculateTagTotals(expenses, 1)[0].percentage, 25);
});

test('latest ten are selected within the month rather than across all dates', () => {
    const records = Array.from({ length: 15 }, (_, index) => ({ id: index + 1, date: `2026-10-${String(index + 1).padStart(2, '0')}` }));
    records.push({ id: 99, date: '2026-11-01' });

    const recent = latestTransactions(recordsForMonth(records, '2026-10'), []);

    assert.equal(recent.length, 10);
    assert.equal(recent[0].record.id, 15);
    assert.equal(recent.at(-1).record.id, 6);
});

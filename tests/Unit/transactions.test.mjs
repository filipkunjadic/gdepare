import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source = await readFile(new URL('../../resources/js/utils/transactions.js', import.meta.url), 'utf8');
const { latestTransactions } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

test('savings never appear in the latest expenses list', () => {
    const records = [{ id: 1, date: '2026-10-01' }, { id: 2, date: '2026-10-02', category: 'savings' }];

    assert.deepEqual(latestTransactions(records, []).filter(item => item.kind === 'expense').map(item => item.record.id), [1]);
    assert.deepEqual(latestTransactions(records, []).filter(item => item.kind === 'savings').map(item => item.record.id), [2]);
});

test('latest savings have their own ten-entry limit and newest-first ordering', () => {
    const savings = Array.from({ length: 12 }, (_, id) => ({ id: id + 1, date: '2026-10-07', category: 'savings' }));
    const result = latestTransactions([...savings, { id: 99, date: '2026-10-08', category: 'expense' }], []);

    assert.deepEqual(result.filter(item => item.kind === 'savings').map(item => item.record.id), [12, 11, 10, 9, 8, 7, 6, 5, 4, 3]);
    assert.equal(result.filter(item => item.kind === 'expense').length, 1);
});

test('dashboard includes latest ten of each kind and sorts the combined list', () => {
    const expenses = Array.from({ length: 15 }, (_, i) => ({ id: i + 1, date: `2026-10-${String(i + 1).padStart(2, '0')}` }));
    const incomes = Array.from({ length: 12 }, (_, i) => ({ id: i + 1, date: `2026-10-${String(i + 2).padStart(2, '0')}` }));
    const original = structuredClone(expenses);
    const result = latestTransactions(expenses, incomes);
    assert.equal(result.length, 20);
    assert.equal(result.filter(item => item.kind === 'expense').length, 10);
    assert.equal(result.filter(item => item.kind === 'income').length, 10);
    assert.equal(result[0].key, 'expense-15');
    assert.equal(result.at(-1).key, 'income-3');
    assert.equal(new Set(result.map(item => item.key)).size, 20);
    assert.deepEqual(expenses, original);
});

test('fewer than ten incomes are retained independently of the expense count', () => {
    assert.equal(latestTransactions(Array.from({ length: 20 }, (_, id) => ({ id, date: '2026-10-07' })), [{ id: 1, date: '2026-01-01' }]).length, 11);
    assert.deepEqual(latestTransactions([], []), []);
});

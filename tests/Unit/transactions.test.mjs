import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source = await readFile(new URL('../../resources/js/utils/transactions.js', import.meta.url), 'utf8');
const { latestTransactions, filterTransactions, collectTagUsage } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

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

const transactions = [
    { kind: 'expense', key: 'expense-1', record: { id: 1, date: '2026-10-01', receiver: { id: 1, name: 'Corner Shop' }, description: 'Weekly groceries', amount: '20.00', currency: 'RSD', tags: [{ id: 1, name: 'Food' }] } },
    { kind: 'expense', key: 'expense-2', record: { id: 2, date: '2026-10-02', receiver: { id: 2, name: 'Corner Shop' }, description: 'Lunch', tags: [{ id: 1, name: 'Food' }] } },
    { kind: 'savings', key: 'expense-3', record: { id: 3, date: '2026-10-03', receiver: { id: 1, name: 'Corner Shop' }, description: 'Reserve', tags: [{ id: 2, name: 'Personal' }] } },
    { kind: 'income', key: 'income-1', record: { id: 1, date: '2026-10-04', description: 'Salary', tags: [{ id: 2, name: 'Personal' }] } },
];

test('transaction filters combine payer identity, tag, category and search', () => {
    assert.deepEqual(filterTransactions(transactions, { payerId: 1, tagId: 1, category: 'expense', search: '  GROCERIES  ' }).map(item => item.key), ['expense-1']);
    assert.deepEqual(filterTransactions(transactions, { payerId: 1, tagId: 2 }).map(item => item.key), ['expense-3']);
    assert.deepEqual(filterTransactions(transactions, { payerId: 1, category: 'income' }), []);
    assert.deepEqual(filterTransactions(transactions, { tagId: 999 }), []);
});

test('transaction search matches payer, description, tag, amount and currency', () => {
    for (const search of ['weekly', '20.00', 'rsd']) {
        assert.deepEqual(filterTransactions(transactions, { search }).map(item => item.key), ['expense-1']);
    }
    assert.equal(filterTransactions(transactions, { search: 'corner shop' }).length, 3);
    assert.equal(filterTransactions(transactions, { search: 'personal' }).length, 2);
    assert.deepEqual(filterTransactions(transactions, { search: 'not a match' }), []);
});

test('transaction sorting does not mutate the source and keeps kinds distinct', () => {
    const original = structuredClone(transactions);
    assert.deepEqual(filterTransactions(transactions).map(item => item.key), ['income-1', 'expense-3', 'expense-2', 'expense-1']);
    assert.deepEqual(filterTransactions(transactions, { order: 'oldest' }).map(item => item.key), ['expense-1', 'expense-2', 'expense-3', 'income-1']);
    assert.deepEqual(transactions, original);
    assert.deepEqual(filterTransactions([]), []);
});

test('tag activity includes income and savings and counts each transaction once', () => {
    const expenses = transactions.filter(item => item.kind !== 'income').map(item => item.record);
    const incomes = [transactions[3].record, { id: 2, date: '2026-10-02', tags: [{ id: 1 }, { id: 1 }] }];
    const usage = collectTagUsage(expenses, incomes);
    assert.deepEqual(usage.get(1), { count: 3, lastDate: '2026-10-02' });
    assert.deepEqual(usage.get(2), { count: 2, lastDate: '2026-10-04' });
    assert.equal(usage.has(999), false);
    assert.deepEqual(collectTagUsage([{ date: '2026-10-01' }], []), new Map());
});

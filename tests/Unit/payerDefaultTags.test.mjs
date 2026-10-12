import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../../resources/js/utils/payerDefaultTags.js', import.meta.url), 'utf8');
const { createPayerTagSelection } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const shop = { id: 1, default_tags: [{ name: 'Food' }, { name: 'Household' }] };
const gym = { id: 2, default_tags: [{ name: 'Health' }] };

test('choosing a payer adds its defaults alongside manually selected tags', () => {
    const selection = createPayerTagSelection(['Work']);

    assert.deepEqual(selection.select(shop), { tags: ['Work', 'Food', 'Household'], skipped: 0 });
});

test('switching payers replaces only automatically added tags', () => {
    const selection = createPayerTagSelection(['Work', 'Food']);
    selection.select(shop);
    selection.update(['Work', 'Food', 'Household', 'Travel']);

    assert.deepEqual(selection.select(gym), { tags: ['Work', 'Food', 'Travel', 'Health'], skipped: 0 });
});

test('clearing a payer or choosing one without defaults removes its automatic tags', () => {
    for (const nextPayer of [null, { id: 3, default_tags: [] }, { id: 4 }]) {
        const selection = createPayerTagSelection(['Work']);
        selection.select(shop);

        assert.deepEqual(selection.select(nextPayer), { tags: ['Work'], skipped: 0 });
    }
});

test('removing defaults is respected when the same payer is matched again', () => {
    const selection = createPayerTagSelection();
    selection.select(shop);
    selection.update(['Food']);

    assert.deepEqual(selection.select({ ...shop }), { tags: ['Food'], skipped: 0 });
    selection.update([]);
    assert.deepEqual(selection.select(shop), { tags: [], skipped: 0 });
});

test('manually readding a removed default preserves it when switching payers', () => {
    const selection = createPayerTagSelection();
    selection.select(shop);
    selection.update(['Household']);
    selection.update(['Household', 'Food']);

    assert.deepEqual(selection.select(gym), { tags: ['Food', 'Health'], skipped: 0 });
});

test('opening an existing transaction never replaces its saved tags with current defaults', () => {
    const selection = createPayerTagSelection(['Historical tag'], shop.id);

    assert.deepEqual(selection.select(shop), { tags: ['Historical tag'], skipped: 0 });
    assert.deepEqual(selection.select(gym), { tags: ['Historical tag', 'Health'], skipped: 0 });
});

test('defaults loaded after manual selection keep the users choices and do not duplicate names', () => {
    const selection = createPayerTagSelection();
    selection.update(['food', 'Travel']);

    assert.deepEqual(selection.select(shop), { tags: ['food', 'Travel', 'Household'], skipped: 0 });
});

test('switching payers retains shared defaults once and preserves names containing commas', () => {
    const selection = createPayerTagSelection(['Personal, shared']);
    selection.select(shop);

    assert.deepEqual(selection.select({ id: 2, default_tags: [{ name: 'Food' }, { name: 'Food' }, { name: 'Other, supplies' }] }), {
        tags: ['Personal, shared', 'Food', 'Other, supplies'], skipped: 0,
    });
});

test('automatic defaults respect the tag limit and never discard manual tags', () => {
    const manualTags = Array.from({ length: 19 }, (_, index) => `Manual ${index}`);
    const selection = createPayerTagSelection(manualTags);

    assert.deepEqual(selection.select(shop), { tags: [...manualTags, 'Food'], skipped: 1 });
    assert.deepEqual(selection.select(gym), { tags: [...manualTags, 'Health'], skipped: 0 });
    assert.deepEqual(manualTags.length, 19);
    selection.select(shop);
    selection.update(manualTags);
    assert.deepEqual(selection.select(shop), { tags: manualTags, skipped: 0 });
});

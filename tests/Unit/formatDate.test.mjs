import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source = await readFile(new URL('../../resources/js/utils/formatDate.js', import.meta.url), 'utf8');
const { formatDate, parseDate } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

test('dates use the selected format without timezone conversion', () => {
    for (const [format, expected] of [['d.m.Y', '07.10.2026'], ['d/m/Y', '07/10/2026'], ['m/d/Y', '10/07/2026'], ['Y-m-d', '2026-10-07']]) {
        assert.equal(formatDate('2026-10-07', format), expected);
        assert.equal(parseDate(expected, format), '2026-10-07');
    }
    assert.equal(formatDate('2026-10-07', 'bad'), '07.10.2026');
    assert.equal(formatDate(null), '');
});

test('date entry rejects impossible dates and accepts leap days', () => {
    for (const value of ['31.02.2026', '29.02.2025', '32.01.2026', '01.13.2026', 'garbage', '']) assert.equal(parseDate(value), null);
    assert.equal(parseDate('29.02.2024'), '2024-02-29');
    assert.equal(parseDate('1.2.2026'), '2026-02-01');
});

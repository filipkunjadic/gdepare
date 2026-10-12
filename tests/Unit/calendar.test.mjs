import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../../resources/js/utils/calendar.js', import.meta.url), 'utf8');
const { calendarWeeks, shiftCalendarDate, calendarDateForKey } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

test('calendar shows six Monday-first weeks including neighboring months', () => {
    const weeks = calendarWeeks('2026-10');

    assert.equal(weeks.length, 6);
    assert.ok(weeks.every(week => week.length === 7));
    assert.deepEqual(weeks[0], ['2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04']);
    assert.equal(weeks.at(-1).at(-1), '2026-11-08');
    assert.equal(new Set(weeks.flat()).size, 42);
});

test('calendar includes leap day only in leap years', () => {
    const leapDays = calendarWeeks('2024-02').flat().filter(day => day.startsWith('2024-02'));
    const ordinaryDays = calendarWeeks('2025-02').flat().filter(day => day.startsWith('2025-02'));

    assert.equal(leapDays.length, 29);
    assert.equal(leapDays.at(-1), '2024-02-29');
    assert.equal(ordinaryDays.length, 28);
    assert.equal(ordinaryDays.at(-1), '2025-02-28');
});

for (const [date, months, expected] of [
    ['2026-01-31', 1, '2026-02-28'],
    ['2024-03-31', -1, '2024-02-29'],
    ['2024-02-29', 12, '2025-02-28'],
    ['2026-12-15', 1, '2027-01-15'],
    ['2026-01-15', -1, '2025-12-15'],
    ['0099-12-31', 1, '0100-01-31'],
]) {
    test(`month navigation from ${date} by ${months} clamps to an available day`, () => {
        assert.equal(shiftCalendarDate(date, { months }), expected);
    });
}

for (const [key, date, shiftKey, expected] of [
    ['ArrowLeft', '2026-10-01', false, '2026-09-30'],
    ['ArrowRight', '2026-12-31', false, '2027-01-01'],
    ['ArrowUp', '2026-10-05', false, '2026-09-28'],
    ['ArrowDown', '2026-10-29', false, '2026-11-05'],
    ['Home', '2026-10-11', false, '2026-10-05'],
    ['End', '2026-10-07', false, '2026-10-11'],
    ['PageUp', '2026-03-31', false, '2026-02-28'],
    ['PageDown', '2026-10-31', false, '2026-11-30'],
    ['PageUp', '2024-02-29', true, '2023-02-28'],
    ['PageDown', '2024-02-29', true, '2025-02-28'],
]) {
    test(`${shiftKey ? 'Shift+' : ''}${key} moves the calendar focus from ${date} to ${expected}`, () => {
        assert.equal(calendarDateForKey(date, key, shiftKey), expected);
    });
}

test('calendar navigation stays within four-digit years and rejects invalid dates', () => {
    assert.equal(shiftCalendarDate('0001-01-01', { days: -1 }), null);
    assert.equal(shiftCalendarDate('9999-12-31', { days: 1 }), null);
    assert.equal(shiftCalendarDate('0001-01-15', { months: -1 }), null);
    assert.equal(shiftCalendarDate('9999-12-15', { months: 1 }), null);
    assert.equal(shiftCalendarDate('2026-02-30'), null);
    assert.equal(shiftCalendarDate('invalid'), null);
    assert.deepEqual(calendarWeeks('0000-01'), []);
    assert.deepEqual(calendarWeeks('2026-13'), []);
    assert.equal(calendarWeeks('0001-01')[0][0], '0001-01-01');
    assert.equal(calendarWeeks('9999-12').flat().filter(Boolean).at(-1), '9999-12-31');
    assert.equal(calendarDateForKey('2026-10-07', 'Tab'), null);
});

test('day navigation is unaffected by local timezone and daylight saving transitions', () => {
    const originalTimezone = process.env.TZ;
    try {
        for (const timezone of ['Europe/Belgrade', 'America/Los_Angeles', 'Pacific/Auckland']) {
            process.env.TZ = timezone;
            assert.equal(shiftCalendarDate('2026-03-29', { days: 1 }), '2026-03-30');
            assert.equal(shiftCalendarDate('2026-10-25', { days: -1 }), '2026-10-24');
            assert.equal(calendarWeeks('2026-10')[0][0], '2026-09-28');
        }
    } finally {
        if (originalTimezone === undefined) delete process.env.TZ;
        else process.env.TZ = originalTimezone;
    }
});

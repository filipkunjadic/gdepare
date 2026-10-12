function isoDate(date) {
    const year = date.getUTCFullYear();
    return !Number.isNaN(date.getTime()) && year >= 1 && year <= 9999 ? date.toISOString().slice(0, 10) : null;
}

export function shiftCalendarDate(value, { days = 0, months = 0 } = {}) {
    const date = new Date(`${value}T12:00:00Z`);
    if (isoDate(date) !== value) return null;
    if (months) {
        const day = date.getUTCDate();
        date.setUTCDate(1);
        date.setUTCMonth(date.getUTCMonth() + months);
        const monthEnd = new Date(date);
        monthEnd.setUTCMonth(monthEnd.getUTCMonth() + 1, 0);
        date.setUTCDate(Math.min(day, monthEnd.getUTCDate()));
    }
    date.setUTCDate(date.getUTCDate() + days);
    return isoDate(date);
}

export function calendarWeeks(month) {
    const first = `${month}-01`;
    const firstDate = new Date(`${first}T12:00:00Z`);
    if (isoDate(firstDate) !== first) return [];
    const offset = (firstDate.getUTCDay() + 6) % 7;
    return Array.from({ length: 6 }, (_, week) => Array.from({ length: 7 }, (_, day) =>
        shiftCalendarDate(first, { days: week * 7 + day - offset })));
}

export function calendarDateForKey(value, key, shiftKey = false) {
    const weekday = (new Date(`${value}T12:00:00Z`).getUTCDay() + 6) % 7;
    const moves = {
        ArrowLeft: { days: -1 },
        ArrowRight: { days: 1 },
        ArrowUp: { days: -7 },
        ArrowDown: { days: 7 },
        Home: { days: -weekday },
        End: { days: 6 - weekday },
        PageUp: { months: shiftKey ? -12 : -1 },
        PageDown: { months: shiftKey ? 12 : 1 },
    };
    return moves[key] ? shiftCalendarDate(value, moves[key]) : null;
}

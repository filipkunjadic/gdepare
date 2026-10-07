export function currentMonth() {
    const today = new Date();
    return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
}

export function isValidMonth(month) {
    return typeof month === 'string' && /^(?!0000)\d{4}-(0[1-9]|1[0-2])$/.test(month);
}

export function shiftMonth(month, offset) {
    const [year, number] = month.split('-').map(Number);
    const index = year * 12 + number - 1 + offset;
    return `${String(Math.floor(index / 12)).padStart(4, '0')}-${String(index % 12 + 1).padStart(2, '0')}`;
}

export function recordsForMonth(records, month) {
    return records.filter(record => record.date.startsWith(`${month}-`));
}

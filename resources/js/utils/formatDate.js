const formats = ['d.m.Y', 'd/m/Y', 'm/d/Y', 'Y-m-d'];
export function formatDate(value, format = 'd.m.Y') {
    if (!value) return '';
    const [Y, m, d] = value.slice(0, 10).split('-');
    if (!Y || !m || !d) return '';
    return (formats.includes(format) ? format : 'd.m.Y').replace(/[Ymd]/g, token => ({ Y, m, d })[token]);
}

export function parseDate(value, format = 'd.m.Y') {
    const tokens = (formats.includes(format) ? format : 'd.m.Y').split(/[./-]/);
    const parts = value.split(/[./-]/);
    if (parts.length !== 3) return null;
    const date = Object.fromEntries(tokens.map((token, index) => [token, parts[index]]));
    if (!/^\d{4}$/.test(date.Y) || !/^\d{1,2}$/.test(date.m) || !/^\d{1,2}$/.test(date.d)) return null;
    const iso = `${date.Y}-${date.m.padStart(2, '0')}-${date.d.padStart(2, '0')}`;
    const parsed = new Date(`${iso}T12:00:00Z`);
    return !Number.isNaN(parsed.getTime()) && parsed.toISOString().slice(0, 10) === iso ? iso : null;
}

export function todayDate() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

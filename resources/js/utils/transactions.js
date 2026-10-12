function newestFirst(a, b) {
    return b.date.localeCompare(a.date) || b.id - a.id;
}

export function latestTransactions(expenses, incomes, limit = 10) {
    return [
        ...[...expenses].filter(record => record.category !== 'savings').sort(newestFirst).slice(0, limit)
            .map(record => ({ kind: 'expense', record, key: `expense-${record.id}` })),
        ...[...incomes].sort(newestFirst).slice(0, limit)
            .map(record => ({ kind: 'income', record, key: `income-${record.id}` })),
        ...[...expenses].filter(record => record.category === 'savings').sort(newestFirst).slice(0, limit)
            .map(record => ({ kind: 'savings', record, key: `expense-${record.id}` })),
    ].sort((a, b) => newestFirst(a.record, b.record) || a.kind.localeCompare(b.kind));
}

export function filterTransactions(transactions, { category = 'all', payerId = null, tagId = null, search = '', order = 'newest' } = {}) {
    const query = search.trim().toLocaleLowerCase();
    return transactions.filter(({ kind, record }) => {
        if (category !== 'all' && kind !== category) return false;
        if (payerId !== null && (kind === 'income' || record.receiver?.id !== payerId)) return false;
        if (tagId !== null && !record.tags?.some(tag => tag.id === tagId)) return false;
        return !query || [record.receiver?.name, record.description, record.currency, record.amount, ...(record.tags ?? []).map(tag => tag.name)]
            .filter(Boolean).join(' ').toLocaleLowerCase().includes(query);
    }).sort((a, b) => (order === 'oldest' ? -1 : 1) * (newestFirst(a.record, b.record) || a.kind.localeCompare(b.kind)));
}

export function collectTagUsage(expenses, incomes) {
    const usage = new Map();
    for (const record of [...expenses, ...incomes]) {
        for (const tagId of new Set((record.tags ?? []).map(tag => tag.id))) {
            if (!usage.has(tagId)) usage.set(tagId, { count: 0, lastDate: null });
            const tag = usage.get(tagId);
            tag.count++;
            if (!tag.lastDate || record.date > tag.lastDate) tag.lastDate = record.date;
        }
    }
    return usage;
}

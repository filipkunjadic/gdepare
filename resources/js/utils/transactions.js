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

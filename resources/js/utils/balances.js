function toCents(amount) {
    const [whole, fraction = ''] = String(amount).split('.');
    return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
}

function toDecimal(cents) {
    const sign = cents < 0n ? '-' : '';
    const absolute = cents < 0n ? -cents : cents;
    return `${sign}${absolute / 100n}.${String(absolute % 100n).padStart(2, '0')}`;
}

export function calculateTagTotals(expenses, tagId) {
    return calculateExpenseTotals(expenses, expense => expense.tags.some(tag => tag.id === tagId));
}

export function calculatePayerTotals(expenses, payerId) {
    return calculateExpenseTotals(expenses, expense => expense.receiver.id === payerId);
}

export function calculateSpendingBreakdown(expenses, currency) {
    const groups = new Map();
    let total = 0n;
    for (const expense of expenses.filter(record => record.currency === currency && record.category !== 'savings')) {
        const cents = toCents(expense.amount);
        total += cents;
        const id = expense.receiver.id;
        if (!groups.has(id)) groups.set(id, { id, label: expense.receiver.name, cents: 0n });
        groups.get(id).cents += cents;
    }
    const sorted = [...groups.values()].sort((a, b) => a.cents === b.cents ? a.id - b.id : a.cents > b.cents ? -1 : 1);
    const top = sorted.slice(0, 5);
    if (sorted.length > 5) {
        top.push({ id: null, label: 'Other payers', cents: sorted.slice(5).reduce((sum, group) => sum + group.cents, 0n) });
    }
    return top.map(group => ({
        id: group.id,
        label: group.label,
        amount: toDecimal(group.cents),
        percentage: total === 0n ? 0 : Number(group.cents) / Number(total) * 100,
    }));
}

function calculateExpenseTotals(expenses, matches) {
    const totals = new Map();
    for (const expense of expenses.filter(record => record.category !== 'savings')) {
        if (!totals.has(expense.currency)) {
            totals.set(expense.currency, { total: 0n, selected: 0n, count: 0 });
        }
        const total = totals.get(expense.currency);
        const amount = toCents(expense.amount);
        total.total += amount;
        if (matches(expense)) {
            total.selected += amount;
            total.count++;
        }
    }

    return [...totals.entries()]
        .filter(([, total]) => total.count > 0)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([currency, total]) => ({
            currency,
            amount: toDecimal(total.selected),
            total: toDecimal(total.total),
            percentage: total.total === 0n ? 0
                : Number((total.selected * 10000n + total.total / 2n) / total.total) / 100,
        }));
}

export function calculateBalances(incomes, expenses) {
    const totals = new Map();
    for (const [kind, records] of [['income', incomes], ['expenses', expenses]]) {
        for (const record of records) {
            if (!totals.has(record.currency)) {
                totals.set(record.currency, { currency: record.currency, income: 0n, expenses: 0n, savings: 0n });
            }
            const category = kind === 'expenses' && record.category === 'savings' ? 'savings' : kind;
            totals.get(record.currency)[category] += toCents(record.amount);
        }
    }

    return [...totals.values()].sort((a, b) => a.currency.localeCompare(b.currency)).map(total => ({
        currency: total.currency,
        income: toDecimal(total.income),
        expenses: toDecimal(total.expenses),
        savings: toDecimal(total.savings),
        balance: toDecimal(total.income - total.expenses - total.savings),
        negative: total.expenses + total.savings > total.income,
    }));
}

export function calculateSavingsDisplay(records, baseCurrency) {
    const savings = records.filter(record => record.category === 'savings' && record.currency === baseCurrency);
    const euros = savings.filter(record => record.foreign_currency === 'EUR' && record.foreign_amount != null);
    const showingEuros = euros.length > 0;
    const amount = (showingEuros ? euros : savings).reduce((sum, record) =>
        sum + toCents(showingEuros ? record.foreign_amount : record.amount), 0n);

    return {
        amount: toDecimal(amount),
        currency: showingEuros ? 'EUR' : baseCurrency,
        missingCount: showingEuros ? savings.length - euros.length : 0,
    };
}

export function calculateTransactionGroups(records, kind, groupBy = 'source') {
    const groups = new Map();
    const totals = new Map();

    for (const record of records) {
        if (record.category === 'savings') continue;
        const cents = toCents(record.amount);
        totals.set(record.currency, (totals.get(record.currency) ?? 0n) + cents);
        const identities = groupBy === 'tag'
            ? [...new Map((record.tags ?? []).map(tag => [tag.id, { id: tag.id, label: tag.name, icon: tag.icon ?? null, color: tag.color ?? null }])).values()]
            : kind === 'income'
            ? [{ id: record.description?.trim() || '', label: record.description?.trim() || 'Unspecified income' }]
            : [{ id: record.receiver.id, label: record.receiver.name }];
        for (const identity of identities) {
            const key = JSON.stringify([record.currency, identity.id]);
            if (!groups.has(key)) groups.set(key, { key, ...identity, currency: record.currency, cents: 0n, count: 0 });
            const group = groups.get(key);
            group.cents += cents;
            group.count++;
        }
    }

    return [...groups.values()]
        .sort((a, b) => a.currency.localeCompare(b.currency)
            || (a.cents === b.cents ? a.label.localeCompare(b.label) : a.cents > b.cents ? -1 : 1))
        .map(({ cents, ...group }) => ({
            ...group,
            amount: toDecimal(cents),
            percentage: totals.get(group.currency) === 0n ? 0
                : Number((cents * 10000n + totals.get(group.currency) / 2n) / totals.get(group.currency)) / 100,
        }));
}

export function formatAmount(amount, currency) {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency,
    }).format(amount);
}

export function formatAmountNumber(amount, currency) {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency,
    }).formatToParts(amount)
        .filter(part => part.type !== 'currency' && part.type !== 'literal')
        .map(part => part.value)
        .join('');
}

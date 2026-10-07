import { session } from '../session.js';

export async function getIncomes() {
    const response = await fetch(session.urls.incomes, {
        headers: {
            Accept: 'application/json',
        },
    });

    if (response.status === 401) {
        window.dispatchEvent(new Event('auth:expired'));
        throw new Error('Your session has expired. Please log in again.');
    }

    if (!response.ok) {
        throw new Error('Could not load incomes.');
    }

    const incomes = await response.json();

    if (!Array.isArray(incomes)) {
        throw new Error('Expected an array of incomes.');
    }

    return incomes;
}

export function createIncome(incomeData) {
    return saveIncome(session.urls.createIncome, 'POST', incomeData);
}

export function updateIncome(incomeId, incomeData) {
    return saveIncome(session.urls.updateIncome.replace('__ID__', encodeURIComponent(incomeId)), 'PATCH', incomeData);
}

export function deleteIncome(incomeId) {
    return saveIncome(session.urls.deleteIncome.replace('__ID__', encodeURIComponent(incomeId)), 'DELETE');
}

async function saveIncome(url, method, incomeData) {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': session.csrfToken,
        },
        body: JSON.stringify(incomeData),
    });

    if (response.status === 401) {
        window.dispatchEvent(new Event('auth:expired'));
        throw new Error('Your session has expired. Please log in again.');
    }

    const result = await response.json().catch(() => null);

    if (!response.ok) {
        const error = new Error(response.status === 404
            ? 'This record no longer exists. Close the form and refresh the list.'
            : response.status === 419
            ? 'Your session has expired. Refresh the page and try again.'
            : `Could not ${method === 'DELETE' ? 'delete' : 'save'} the income. Please try again.`);
        error.errors = response.status === 422 ? result?.errors : undefined;
        throw error;
    }

    return result;
}


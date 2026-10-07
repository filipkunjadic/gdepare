import { session } from '../session.js';

export async function getExpenses() {
    const response = await fetch(session.urls.expenses, {
        headers: {
            Accept: 'application/json',
        },
    });

    if (response.status === 401) {
        window.dispatchEvent(new Event('auth:expired'));
        throw new Error('Your session has expired. Please log in again.');
    }

    if (!response.ok) {
        throw new Error('Could not load expenses.');
    }

    const expenses = await response.json();

    if (!Array.isArray(expenses)) {
        throw new Error('Expected an array of expenses.');
    }

    return expenses;
}

export function createExpense(expenseData) {
    return saveExpense(session.urls.createExpense, 'POST', expenseData);
}

export function updateExpense(expenseId, expenseData) {
    return saveExpense(session.urls.updateExpense.replace('__ID__', encodeURIComponent(expenseId)), 'PATCH', expenseData);
}

export function deleteExpense(expenseId) {
    return saveExpense(session.urls.deleteExpense.replace('__ID__', encodeURIComponent(expenseId)), 'DELETE');
}

async function saveExpense(url, method, expenseData) {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': session.csrfToken,
        },
        body: JSON.stringify(expenseData),
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
            : `Could not ${method === 'DELETE' ? 'delete' : 'save'} the expense. Please try again.`);
        error.errors = response.status === 422 ? result?.errors : undefined;
        throw error;
    }

    return result;
}



import { session } from '../session.js';

async function post(url, data = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': session.csrfToken,
        },
        body: JSON.stringify(data),
    });
    const result = await response.json();

    if (!response.ok) {
        const error = new Error(response.status === 419
            ? 'Your session has expired. Refresh the page and try again.'
            : result.message || 'The request failed. Please try again.');
        error.errors = result.errors;
        throw error;
    }

    return result;
}

export function login(credentials) {
    return post(session.urls.login, credentials);
}

export function logout() {
    return post(session.urls.logout);
}

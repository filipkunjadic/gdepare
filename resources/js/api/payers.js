import { session } from '../session.js';

export async function getPayers() {
    const response = await fetch(session.urls.payers, { headers: { Accept: 'application/json' } });
    if (response.status === 401) {
        window.dispatchEvent(new Event('auth:expired'));
        throw new Error('Your session has expired. Please log in again.');
    }
    if (!response.ok) throw new Error('Could not load payers. Please try again.');
    const payers = await response.json();
    if (!Array.isArray(payers)) throw new Error('Expected an array of payers.');
    return payers;
}

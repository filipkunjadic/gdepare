import { session } from '../session.js';

export async function updateSettings(data) {
    const response = await fetch(session.urls.updateSettings, {
        method: 'PATCH',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': session.csrfToken },
        body: JSON.stringify(data),
    });
    if (response.status === 401) {
        window.dispatchEvent(new Event('auth:expired'));
        throw new Error('Your session has expired. Please log in again.');
    }
    const result = await response.json().catch(() => null);
    if (!response.ok) {
        throw new Error(result?.errors ? Object.values(result.errors).flat().join(' ') : response.status === 419 ? 'Your session has expired. Refresh the page.' : 'Could not save settings. Please try again.');
    }
    return result;
}

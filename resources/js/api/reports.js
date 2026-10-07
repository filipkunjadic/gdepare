import { session } from '../session.js';

export async function getReport(parameters) {
    const response = await fetch(`${session.urls.downloadReport}?${new URLSearchParams(parameters)}`, {
        headers: { Accept: 'application/json' },
    });
    if (response.status === 401) {
        window.dispatchEvent(new Event('auth:expired'));
        throw new Error('Your session has expired. Please log in again.');
    }
    if (!response.ok) {
        const result = await response.json().catch(() => null);
        throw new Error(result?.errors ? Object.values(result.errors).flat().join(' ') : 'Could not generate the report. Please try again.');
    }
    if (!response.headers.get('Content-Type')?.includes('application/pdf')) {
        throw new Error('The server did not return a PDF. Refresh the page and try again.');
    }
    return response.blob();
}

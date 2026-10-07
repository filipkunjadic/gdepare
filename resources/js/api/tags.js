import { session } from '../session.js';

export const getTags = () => request(session.urls.tags);
export const createTag = data => request(session.urls.createTag, 'POST', data);
export const updateTag = (id, data) => request(session.urls.updateTag.replace('__ID__', encodeURIComponent(id)), 'PATCH', data);
export const deleteTag = id => request(session.urls.deleteTag.replace('__ID__', encodeURIComponent(id)), 'DELETE');

async function request(url, method = 'GET', data) {
    const response = await fetch(url, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': session.csrfToken },
        body: data === undefined ? undefined : JSON.stringify(data),
    });
    if (response.status === 401) {
        window.dispatchEvent(new Event('auth:expired'));
        throw new Error('Your session has expired. Please log in again.');
    }
    const result = await response.json().catch(() => null);
    if (!response.ok) {
        throw new Error((result?.errors ? Object.values(result.errors).flat().join(' ') : null) ?? (response.status === 404
            ? 'This tag no longer exists. Refresh the list.'
            : response.status === 419 ? 'Your session has expired. Refresh the page.'
            : 'Could not complete the tag request. Please try again.'));
    }
    if (method === 'GET' && !Array.isArray(result)) throw new Error('Expected an array of tags.');
    return result;
}

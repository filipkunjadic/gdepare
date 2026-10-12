import { session } from '../session.js';

export const createPayer = data => payerRequest(session.urls.createPayer, 'POST', payerFormData(data));
export const deletePayer = id => payerRequest(session.urls.deletePayer.replace('__ID__', encodeURIComponent(id)), 'DELETE');

export async function updatePayer(id, values) {
    const data = payerFormData(values);
    data.append('_method', 'PATCH');
    return payerRequest(session.urls.updatePayer.replace('__ID__', encodeURIComponent(id)), 'POST', data);
}

function payerFormData({ name, image, removeImage = false, defaultTags }) {
    const data = new FormData();
    data.append('name', name);
    data.append('remove_image', removeImage ? '1' : '0');
    if (image) data.append('image', image);
    if (defaultTags !== undefined) {
        if (defaultTags.length) {
            for (const tag of defaultTags) data.append('default_tags[]', tag);
        } else {
            data.append('default_tags', '');
        }
    }
    return data;
}

export async function uploadPayerImage(id, image) {
    const data = new FormData();
    data.append('image', image);
    return payerRequest(session.urls.uploadPayerImage.replace('__ID__', encodeURIComponent(id)), 'POST', data);
}

export const deletePayerImage = id => payerRequest(session.urls.deletePayerImage.replace('__ID__', encodeURIComponent(id)), 'DELETE');

async function payerRequest(url, method, body) {
    const response = await fetch(url, {
        method,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': session.csrfToken },
        body,
    });
    if (response.status === 401) {
        window.dispatchEvent(new Event('auth:expired'));
        throw new Error('Your session has expired. Please log in again.');
    }
    const result = await response.json().catch(() => null);
    if (!response.ok) {
        throw new Error((result?.errors ? Object.values(result.errors).flat().join(' ') : null) ?? (response.status === 404
            ? 'This payer no longer exists. Refresh the list.'
            : response.status === 419 ? 'Your session has expired. Refresh the page.'
            : response.status === 413 ? 'Choose an image smaller than 2 MB.'
            : 'Could not complete the payer request. Please try again.'));
    }
    return result;
}

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

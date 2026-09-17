import axios from 'axios';

/**
 * Single axios client for the whole admin SPA.
 *
 * Laravel answers a failed validation with a redirect unless the request is
 * recognised as XHR wanting JSON, so `Accept` and `X-Requested-With` are as
 * important as the CSRF token here (PM-N-03).
 */
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const client = axios.create({
    baseURL: '/admin/api',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
    },
});

/** GET the list plus the allowed currency and country codes, in one request (PM-N-01). */
export async function fetchPaymentMethods() {
    const { data } = await client.get('/payment-methods');

    return {
        methods: data.data ?? [],
        currencies: data.currencies ?? [],
        countries: data.countries ?? [],
    };
}

export async function createPaymentMethod(payload) {
    const { data } = await client.post('/payment-methods', payload);

    return data.data;
}

export async function updatePaymentMethod(id, payload) {
    const { data } = await client.put(`/payment-methods/${id}`, payload);

    return data.data;
}

/** Deactivating is its own endpoint and never goes through update or delete (PM-C-04). */
export async function setPaymentMethodActive(id, active) {
    const { data } = await client.patch(`/payment-methods/${id}/active`, { active });

    return data.data;
}

/** Only ever called from DeleteDialog's confirmation (PM-C-03). */
export async function deletePaymentMethod(id) {
    const { data } = await client.delete(`/payment-methods/${id}`);

    return data.message ?? 'Payment method deleted.';
}

/**
 * Turns a rejected 422 into a `{ field: [message…] }` map.
 * Laravel reports nested failures as `currencies.0`; the form knows only the
 * field, so every key is folded back onto its first segment (PM-FR-58).
 */
export function validationErrors(error) {
    if (error?.response?.status !== 422) {
        return null;
    }

    const errors = error.response.data?.errors ?? {};

    const byField = Object.entries(errors).reduce((carry, [key, messages]) => {
        const field = key.split('.')[0];

        carry[field] = [...(carry[field] ?? []), ...messages];

        return carry;
    }, {});

    // A 422 without a usable map is not a field failure; the caller must show a banner instead.
    return Object.keys(byField).length > 0 ? byField : null;
}

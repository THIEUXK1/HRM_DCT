import axios from 'axios';

const api = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
});

// ── Request interceptor: attach auth headers ──────────────────────────────────
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('hcm_token');
    const companyId = localStorage.getItem('hcm_company_id');
    const expiresAt = localStorage.getItem('hcm_token_expires_at');

    // Client-side expiry check — silently clear before sending
    if (expiresAt && new Date(expiresAt) < new Date()) {
        localStorage.removeItem('hcm_token');
        localStorage.removeItem('hcm_token_expires_at');
        window.location.href = '/app/login?reason=expired';
        return Promise.reject(new Error('Token expired'));
    }

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    if (companyId) {
        config.headers['X-Company-Id'] = companyId;
    }

    return config;
});

// ── Response interceptor: handle 401 (expired or invalid token) ───────────────
api.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const code   = error.response?.data?.code;

        if (status === 401) {
            const isLoginRoute = error.config?.url?.includes('/auth/login');

            if (!isLoginRoute) {
                localStorage.removeItem('hcm_token');
                localStorage.removeItem('hcm_token_expires_at');
                localStorage.removeItem('hcm_user');

                const reason = code === 'TOKEN_EXPIRED' ? 'expired' : 'unauthenticated';
                window.location.href = `/app/login?reason=${reason}`;
            }
        }

        return Promise.reject(error);
    }
);

export default api;

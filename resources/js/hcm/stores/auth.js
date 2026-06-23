import { defineStore, acceptHMRUpdate } from 'pinia';
import api from '../api/client';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token:     localStorage.getItem('hcm_token'),
        expiresAt: localStorage.getItem('hcm_token_expires_at'),
        user:        null,
        permissions: [],
        roles:       [],
        companyId:   localStorage.getItem('hcm_company_id'),
    }),
    getters: {
        isAuthenticated: (state) => !!state.token,
        mustChangePassword: (state) => !!state.user?.must_change_password,
        isTokenExpired: (state) => {
            if (!state.expiresAt) return false;
            return new Date(state.expiresAt) < new Date();
        },
        tokenExpiresIn: (state) => {
            if (!state.expiresAt) return null;
            const diff = new Date(state.expiresAt) - new Date();
            return diff > 0 ? Math.floor(diff / 60000) : 0; // minutes
        },
    },
    actions: {
        async login(login, password) {
            const identifier = String(login || '').trim();
            const normalizedLogin = identifier.includes('@')
                ? identifier
                : identifier.toUpperCase();

            const { data } = await api.post('/auth/login', { login: normalizedLogin, password });
            const payload = data.data;

            this.token     = payload.token;
            this.expiresAt = payload.expires_at ?? null;

            localStorage.setItem('hcm_token', this.token);
            if (this.expiresAt) {
                localStorage.setItem('hcm_token_expires_at', this.expiresAt);
            }

            this.setUser(payload.user);
            if (this.user?.default_company_id) {
                this.setCompany(this.user.default_company_id);
            }

            return payload;
        },

        async changePassword(currentPassword, password, passwordConfirmation) {
            const { data } = await api.post('/auth/change-password', {
                current_password: currentPassword,
                password,
                password_confirmation: passwordConfirmation,
            });
            this.setUser(data.data.user);
            return data.data;
        },

        async fetchMe() {
            const { data } = await api.get('/auth/me');
            this.setUser(data.data);
            if (data.data.token_expires_at) {
                this.expiresAt = data.data.token_expires_at;
                localStorage.setItem('hcm_token_expires_at', this.expiresAt);
            }
            if (!this.companyId && this.user?.default_company_id) {
                this.setCompany(this.user.default_company_id);
            }
        },

        /** Refresh the token before it expires. Called proactively by the frontend. */
        async rotate() {
            try {
                const { data } = await api.post('/auth/rotate');
                this.token     = data.data.token;
                this.expiresAt = data.data.expires_at;
                localStorage.setItem('hcm_token', this.token);
                localStorage.setItem('hcm_token_expires_at', this.expiresAt);
            } catch {
                this.logout();
            }
        },

        setCompany(id) {
            this.companyId = String(id);
            localStorage.setItem('hcm_company_id', this.companyId);
        },

        async setCompanyAndRefresh(id) {
            this.setCompany(id);
            if (this.token) {
                await this.fetchMe();
            }
        },

        setUser(user) {
            this.user        = user;
            this.permissions = user?.permissions || [];
            this.roles       = user?.roles || [];
        },

        logout() {
            this.token       = null;
            this.expiresAt   = null;
            this.user        = null;
            this.permissions = [];
            this.roles       = [];
            localStorage.removeItem('hcm_token');
            localStorage.removeItem('hcm_token_expires_at');
            localStorage.removeItem('hcm_company_id');
            localStorage.removeItem('hcm_user');
        },
    },
});

if (import.meta.hot) {
    import.meta.hot.accept(acceptHMRUpdate(useAuthStore, import.meta.hot));
}

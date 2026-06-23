import { defineStore, acceptHMRUpdate } from 'pinia';
import api from '../api/client';
import { ensureArray } from '../composables/usePagination';

export const useAppStore = defineStore('app', {
    state: () => ({
        companies: [],
        loading: false,
        pendingApprovals: 0,
        approvalInbox: [],      // Full inbox cached here for reuse
        inboxLoaded: false,
    }),
    getters: {
        currentCompany: (state) => {
            const id = Number(localStorage.getItem('hcm_company_id'));
            return state.companies.find((c) => c.id === id) ?? state.companies[0] ?? null;
        },
        isMultiCompany: (state) => state.companies.length > 1,
    },
    actions: {
        async loadCompanies() {
            this.loading = true;
            try {
                const { data } = await api.get('/companies');
                this.companies = data.data || [];
            } finally {
                this.loading = false;
            }
        },
        async loadPendingApprovals() {
            try {
                const { data } = await api.get('/approvals/inbox');
                this.approvalInbox    = ensureArray(data.data ?? data);
                this.pendingApprovals = this.approvalInbox.filter((a) => a.instance?.status === 'pending').length;
                this.inboxLoaded      = true;
            } catch {
                this.pendingApprovals = 0;
                this.inboxLoaded      = true;
            }
        },
        /** Refresh inbox without blocking — call after approve/reject actions */
        async refreshInbox() {
            this.inboxLoaded = false;
            await this.loadPendingApprovals();
        },
    },
});

if (import.meta.hot) {
    import.meta.hot.accept(acceptHMRUpdate(useAppStore, import.meta.hot));
}

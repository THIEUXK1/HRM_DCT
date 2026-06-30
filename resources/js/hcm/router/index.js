import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import AppLayout from '../layouts/AppLayout.vue';
import SelfServicePage from '../pages/self-service/SelfServicePage.vue';
import MssPage from '../pages/mss/MssPage.vue';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../pages/LoginPage.vue'),
        meta: { guest: true },
    },
    {
        path: '/change-password',
        name: 'change-password',
        component: () => import('../pages/auth/ChangePasswordPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/careers',
        name: 'careers',
        component: () => import('../pages/recruitment/CareerPage.vue'),
        meta: { public: true },
    },
    {
        path: '/',
        component: AppLayout,
        meta: { requiresAuth: true },
        children: [
            { path: '', name: 'dashboard', component: () => import('../pages/DashboardPage.vue') },
            { path: 'manager-dashboard', name: 'manager-dashboard', component: () => import('../pages/dashboard/ManagerDashboardPage.vue') },
            { path: 'employees', name: 'employees', component: () => import('../pages/employees/EmployeeListPage.vue') },
            { path: 'employees/:id', name: 'employee-detail', component: () => import('../pages/employees/EmployeeDetailPage.vue') },
            { path: 'organization', name: 'organization', component: () => import('../pages/organization/OrganizationPage.vue') },
            { path: 'company-policies', name: 'company-policies', component: () => import('../pages/organization/CompanyPolicyPage.vue') },
            { path: 'contracts', name: 'contracts', component: () => import('../pages/contracts/ContractListPage.vue') },
            { path: 'offboarding', name: 'offboarding', component: () => import('../pages/offboarding/OffboardingPage.vue') },
            { path: 'recruitment', name: 'recruitment', component: () => import('../pages/recruitment/RecruitmentPage.vue') },
            { path: 'candidates', redirect: { name: 'recruitment', query: { tab: 'candidates' } } },
            { path: 'onboarding', name: 'onboarding', component: () => import('../pages/onboarding/OnboardingPage.vue') },
            { path: 'attendance', name: 'attendance', component: () => import('../pages/attendance/AttendancePage.vue') },
            { path: 'work-schedules', name: 'work-schedules', component: () => import('../pages/attendance/WorkSchedulePage.vue') },
            { path: 'attendance-punch', name: 'attendance-punch', component: () => import('../pages/attendance/AttendancePunchPage.vue') },
            { path: 'leave-requests', name: 'leave', component: () => import('../pages/attendance/LeaveListPage.vue') },
            { path: 'attendance-devices', name: 'attendance-devices', component: () => import('../pages/attendance/AttendanceDevicePage.vue') },
            { path: 'attendance-sources', name: 'attendance-sources', component: () => import('../pages/attendance/ZKTimeSourcePage.vue') },
            { path: 'zkteco-sync', name: 'zkteco-sync', component: () => import('../pages/attendance/ZkTecoSyncPage.vue') },
            { path: 'payroll', name: 'payroll', component: () => import('../pages/payroll/PayrollListPage.vue') },
            { path: 'bhxh', name: 'bhxh', component: () => import('../pages/bhxh/BhxhPage.vue') },
            { path: 'bhxh-export', redirect: { name: 'bhxh' } },
            { path: 'approvals', name: 'approvals', component: () => import('../pages/approvals/ApprovalsPage.vue') },
            { path: 'training', name: 'training', component: () => import('../pages/training/TrainingPage.vue') },
            { path: 'competency', name: 'competency', component: () => import('../pages/competency/CompetencyPage.vue') },
            { path: 'performance', name: 'performance', component: () => import('../pages/performance/PerformancePage.vue') },
            { path: 'reports', name: 'reports', component: () => import('../pages/reports/ReportsPage.vue') },
            { path: 'self-service', name: 'self-service', component: SelfServicePage },
            { path: 'mss', name: 'mss', component: MssPage },
            { path: 'settings', name: 'settings', component: () => import('../pages/settings/SettingsPage.vue') },
            { path: 'benefits', name: 'benefits', component: () => import('../pages/benefits/BenefitsPage.vue') },
            { path: 'notifications', name: 'notifications', component: () => import('../pages/notifications/NotificationsPage.vue') },
            { path: 'audit-log', name: 'audit-log', component: () => import('../pages/audit/AuditLogPage.vue') },
            { path: 'user-management', name: 'user-management', component: () => import('../pages/admin/UserManagementPage.vue') },
        ],
    },
];

const router = createRouter({
    history: createWebHistory('/app'),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (to.meta.public) {
        return;
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        if (to.name === 'attendance-punch') {
            return { name: 'login', query: { mode: 'punch', redirect: to.fullPath } };
        }
        return { name: 'login' };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    if (auth.isAuthenticated && !auth.user) {
        try {
            await auth.fetchMe();
        } catch {
            auth.logout();
            return { name: 'login' };
        }
    }

    if (auth.isAuthenticated && auth.mustChangePassword && to.name !== 'change-password') {
        return { name: 'change-password' };
    }

    if (to.name === 'change-password' && auth.isAuthenticated && !auth.mustChangePassword) {
        return { name: 'dashboard' };
    }
});

router.onError((error, to) => {
    const isChunkError = /Failed to fetch dynamically imported module|Importing a module script failed/i.test(
        error?.message || '',
    );
    if (!isChunkError) return;

    const reloadKey = `chunk_reload:${to.fullPath}`;
    if (!sessionStorage.getItem(reloadKey)) {
        sessionStorage.setItem(reloadKey, '1');
        window.location.assign(to.fullPath);
        return;
    }
    sessionStorage.removeItem(reloadKey);
    console.error('Không tải được module frontend. Vui lòng xóa cache trình duyệt (Ctrl+F5).', error);
});

export default router;

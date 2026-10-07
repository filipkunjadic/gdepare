import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const router = createRouter({
    history: createWebHistory(),

    routes: [
        { path: '/settings', name: 'settings', component: () => import('../pages/Settings.vue'), meta: { requiresAuth: true } },
        {
            path: '/',
            name: 'home',
            component: () => import('../pages/Welcome.vue'),
        },
        {
            path: '/login',
            name: 'login',
            component: () => import('../pages/Login.vue'),
            meta: { guestOnly: true },
        },
        {
            path: '/dashboard',
            name: 'dashboard',
            component: () => import('../pages/Dashboard.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/transactions',
            name: 'transactions',
            component: () => import('../pages/Transactions.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/page',
            redirect: { name: 'dashboard' },
        },
        {
            path: '/payers',
            name: 'payers',
            component: () => import('../pages/Payers.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/tags',
            name: 'tags',
            component: () => import('../pages/Tags.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/reports',
            name: 'reports',
            component: () => import('../pages/Reports.vue'),
            meta: { requiresAuth: true },
        },
    ],
});

router.beforeEach((to) => {
    const auth = useAuthStore();
    if (to.meta.requiresAuth && !auth.user) {
        return { name: 'login' };
    }

    if (to.meta.guestOnly && auth.user) {
        return { name: 'dashboard' };
    }
});

export default router;

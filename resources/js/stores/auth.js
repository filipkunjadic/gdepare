import { ref } from 'vue';
import { defineStore } from 'pinia';
import * as authApi from '../api/auth.js';
import { session } from '../session.js';
import { useExpensesStore } from './expenses.js';
import { useIncomesStore } from './incomes.js';
import { usePeriodStore } from './period.js';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(session.user);

    function clearSession() {
        user.value = null;
        useExpensesStore().reset();
        useIncomesStore().reset();
        usePeriodStore().reset();
    }

    async function login(credentials) {
        const result = await authApi.login(credentials);
        useExpensesStore().reset();
        useIncomesStore().reset();
        usePeriodStore().reset();
        session.csrfToken = result.csrfToken;
        user.value = result.user;
    }

    async function logout() {
        const result = await authApi.logout();
        session.csrfToken = result.csrfToken;
        clearSession();
    }

    return { user, login, logout, clearSession };
});

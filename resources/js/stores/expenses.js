import { ref } from 'vue';
import { defineStore } from 'pinia';
import {
    getExpenses,
    createExpense as createExpenseRequest,
    updateExpense as updateExpenseRequest,
    deleteExpense as deleteExpenseRequest,
} from '../api/expenses.js';

export const useExpensesStore = defineStore('expenses', () => {
    const expenses = ref([]);
    const loading = ref(false);
    const error = ref('');
    const loaded = ref(false);
    let generation = 0;

    function reset() {
        generation++;
        expenses.value = [];
        loading.value = false;
        error.value = '';
        loaded.value = false;
    }

    async function loadExpenses(force = false) {
        if (loading.value || (loaded.value && !force)) {
            return;
        }

        loading.value = true;
        error.value = '';
        const requestGeneration = generation;

        try {
            const result = await getExpenses();
            if (requestGeneration !== generation) return;
            expenses.value = result;
            loaded.value = true;
        } catch (requestError) {
            if (requestGeneration !== generation) return;
            error.value = requestError instanceof Error
                ? requestError.message
                : 'Could not load expenses.';
        } finally {
            if (requestGeneration === generation) loading.value = false;
        }
    }

    async function createExpense(expenseData) {
        const requestGeneration = generation;
        const expense = await createExpenseRequest(expenseData);
        if (requestGeneration !== generation) return;

        generation++;
        loading.value = false;
        error.value = '';
        expenses.value = [expense, ...expenses.value].sort((a, b) =>
            b.date.localeCompare(a.date) || b.id - a.id);
        if (!loaded.value) await loadExpenses(true);
        return expense;
    }

    async function updateExpense(expenseId, expenseData) {
        const requestGeneration = generation;
        const result = await updateExpenseRequest(expenseId, expenseData);
        if (requestGeneration !== generation) return;

        generation++;
        loading.value = false;
        error.value = '';
        expenses.value = expenses.value.map(record => record.id === result.id ? result : record)
            .sort((a, b) => b.date.localeCompare(a.date) || b.id - a.id);
        return result;
    }

    async function deleteExpense(expenseId) {
        const requestGeneration = generation;
        await deleteExpenseRequest(expenseId);
        if (requestGeneration !== generation) return;

        generation++;
        loading.value = false;
        error.value = '';
        expenses.value = expenses.value.filter(record => record.id !== expenseId);
    }

    return {
        expenses,
        loading,
        error,
        loaded,
        reset,
        loadExpenses,
        createExpense,
        updateExpense,
        deleteExpense,
    };
});

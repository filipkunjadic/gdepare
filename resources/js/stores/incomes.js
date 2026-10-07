import { ref } from 'vue';
import { defineStore } from 'pinia';
import {
    getIncomes,
    createIncome as createIncomeRequest,
    updateIncome as updateIncomeRequest,
    deleteIncome as deleteIncomeRequest,
} from '../api/incomes.js';

export const useIncomesStore = defineStore('incomes', () => {
    const incomes = ref([]);
    const loading = ref(false);
    const error = ref('');
    const loaded = ref(false);
    let generation = 0;

    function reset() {
        generation++;
        incomes.value = [];
        loading.value = false;
        error.value = '';
        loaded.value = false;
    }

    async function loadIncomes(force = false) {
        if (loading.value || (loaded.value && !force)) {
            return;
        }

        loading.value = true;
        error.value = '';
        const requestGeneration = generation;

        try {
            const result = await getIncomes();
            if (requestGeneration !== generation) return;
            incomes.value = result;
            loaded.value = true;
        } catch (requestError) {
            if (requestGeneration !== generation) return;
            error.value = requestError instanceof Error
                ? requestError.message
                : 'Could not load incomes.';
        } finally {
            if (requestGeneration === generation) loading.value = false;
        }
    }

    async function createIncome(incomeData) {
        const requestGeneration = generation;
        const income = await createIncomeRequest(incomeData);
        if (requestGeneration !== generation) return;

        generation++;
        loading.value = false;
        error.value = '';
        incomes.value = [income, ...incomes.value].sort((a, b) =>
            b.date.localeCompare(a.date) || b.id - a.id);
        if (!loaded.value) await loadIncomes(true);
        return income;
    }

    async function updateIncome(incomeId, incomeData) {
        const requestGeneration = generation;
        const result = await updateIncomeRequest(incomeId, incomeData);
        if (requestGeneration !== generation) return;

        generation++;
        loading.value = false;
        error.value = '';
        incomes.value = incomes.value.map(record => record.id === result.id ? result : record)
            .sort((a, b) => b.date.localeCompare(a.date) || b.id - a.id);
        return result;
    }

    async function deleteIncome(incomeId) {
        const requestGeneration = generation;
        await deleteIncomeRequest(incomeId);
        if (requestGeneration !== generation) return;

        generation++;
        loading.value = false;
        error.value = '';
        incomes.value = incomes.value.filter(record => record.id !== incomeId);
    }

    return {
        incomes,
        loading,
        error,
        loaded,
        reset,
        loadIncomes,
        createIncome,
        updateIncome,
        deleteIncome,
    };
});

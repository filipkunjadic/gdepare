import { ref } from 'vue';
import { defineStore } from 'pinia';
import { currentMonth, isValidMonth, shiftMonth } from '../utils/period.js';

export const usePeriodStore = defineStore('period', () => {
    const month = ref(currentMonth());

    function selectMonth(value) {
        if (isValidMonth(value)) month.value = value;
    }

    function moveMonth(offset) {
        selectMonth(shiftMonth(month.value, offset));
    }

    function reset() {
        month.value = currentMonth();
    }

    return { month, selectMonth, moveMonth, reset };
});

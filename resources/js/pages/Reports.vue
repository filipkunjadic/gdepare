<script setup>
import { ref } from 'vue';
import DashboardLayout from '../components/DashboardLayout.vue';
import { usePeriodStore } from '../stores/period.js';
import { getReport } from '../api/reports.js';

const selectedMonth = usePeriodStore().month;
const period = ref('month');
const year = ref(Number(selectedMonth.slice(0, 4)));
const month = ref(Number(selectedMonth.slice(5)));
const months = Array.from({ length: 12 }, (_, index) => ({ value: index + 1, label: new Intl.DateTimeFormat('en-GB', { month: 'long' }).format(new Date(2000, index, 1)) }));
const generating = ref(false);
const error = ref('');
const message = ref('');
const inputClass = 'mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:outline-2 focus:outline-indigo-600';

async function download() {
    if (generating.value) return;
    generating.value = true;
    error.value = '';
    message.value = '';
    try {
        const parameters = { period: period.value, year: String(year.value) };
        if (period.value === 'month') parameters.month = String(month.value);
        const pdf = await getReport(parameters);
        const url = URL.createObjectURL(pdf);
        const link = document.createElement('a');
        link.href = url;
        link.download = `finance-report-${year.value}${period.value === 'month' ? '-' + String(month.value).padStart(2, '0') : ''}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        message.value = 'Your PDF is ready. Download started.';
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        generating.value = false;
    }
}
</script>

<template>
    <DashboardLayout>
        <main class="mx-auto max-w-[1600px] px-4 py-8 sm:px-6 lg:px-8">
            <h1 class="mb-6 text-2xl font-semibold tracking-tight text-slate-950">Reports</h1>
            <form class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6" :aria-busy="generating" @submit.prevent="download">
                <h2 class="text-base font-semibold text-slate-900">Financial report</h2>
                <p class="mt-2 text-sm text-slate-500">Download your balance summary and all income, expense, and savings transactions as a PDF.</p>
                <fieldset :disabled="generating" class="mt-6 grid gap-4 sm:grid-cols-3">
                    <label class="text-sm font-medium text-slate-700">Period
                        <select v-model="period" :class="inputClass"><option value="month">Monthly</option><option value="year">Yearly</option></select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Year
                        <input v-model="year" type="number" required min="1900" max="9998" step="1" :class="inputClass">
                    </label>
                    <label v-if="period === 'month'" class="text-sm font-medium text-slate-700">Month
                        <select v-model="month" :class="inputClass"><option v-for="item in months" :key="item.value" :value="item.value">{{ item.label }}</option></select>
                    </label>
                </fieldset>
                <p v-if="error" role="alert" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
                <p v-if="message" role="status" class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ message }}</p>
                <button type="submit" :disabled="generating" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-wait disabled:opacity-50">
                    <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    {{ generating ? 'Generating PDF…' : 'Download PDF' }}
                </button>
            </form>
        </main>
    </DashboardLayout>
</template>

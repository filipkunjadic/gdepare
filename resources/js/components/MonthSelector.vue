<script setup>
import { usePeriodStore } from '../stores/period.js';

const period = usePeriodStore();

function selectMonth(event) {
    period.selectMonth(event.target.value);
    event.target.value = period.month;
}
</script>

<template>
    <div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-xs" role="group" aria-label="Select month">
        <button type="button" aria-label="Previous month" :disabled="period.month === '0001-01'" class="flex size-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-indigo-600 disabled:opacity-30" @click="period.moveMonth(-1)">
            <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m14 6-6 6 6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </button>
        <input type="month" aria-label="Month" min="0001-01" max="9999-12" required :value="period.month" class="min-w-0 rounded-md bg-transparent px-1 py-1.5 text-sm font-semibold text-slate-800 focus:outline-2 focus:outline-indigo-600" @change="selectMonth">
        <button type="button" aria-label="Next month" :disabled="period.month === '9999-12'" class="flex size-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-indigo-600 disabled:opacity-30" @click="period.moveMonth(1)">
            <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m10 6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </button>
        <button type="button" class="rounded-lg border-l border-slate-100 px-3 py-2 text-xs font-medium text-indigo-700 hover:bg-indigo-50 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="period.reset">This month</button>
    </div>
</template>

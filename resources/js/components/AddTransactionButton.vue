<script setup>
defineProps({
    kind: { type: String, required: true, validator: value => ['expense', 'savings', 'income'].includes(value) },
});

const labels = { expense: 'Add expense', savings: 'Add savings', income: 'Add income' };
const buttonClasses = {
    expense: 'border-indigo-600 bg-indigo-600 text-white hover:border-indigo-700 hover:bg-indigo-700 focus-visible:outline-indigo-600',
    savings: 'border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100 focus-visible:outline-violet-600',
    income: 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 focus-visible:outline-emerald-600',
};
</script>

<template>
    <button type="button" :class="buttonClasses[kind]" class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl border px-4 text-sm leading-5 font-semibold whitespace-nowrap shadow-xs transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
        <svg aria-hidden="true" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg>
        {{ labels[kind] }}
    </button>
</template>

<script setup>
import { computed } from 'vue';
import { calculateSpendingBreakdown } from '../utils/balances.js';
import MoneyAmount from './MoneyAmount.vue';
import DonutChart from './DonutChart.vue';

const props = defineProps({
    expenses: { type: Array, required: true },
    incomes: { type: Array, required: true },
    savings: { type: Array, required: true },
    balances: { type: Array, required: true },
});
const emit = defineEmits(['payer']);
const total = computed(() => props.balances.find(item => item.currency === 'RSD')
    ?? { currency: 'RSD', income: '0.00', expenses: '0.00', savings: '0.00' });
const colors = ['stroke-indigo-500', 'stroke-sky-500', 'stroke-violet-400', 'stroke-amber-400', 'stroke-rose-400', 'stroke-slate-300'];
const dots = ['bg-indigo-500', 'bg-sky-500', 'bg-violet-400', 'bg-amber-400', 'bg-rose-400', 'bg-slate-300'];
const spending = computed(() => calculateSpendingBreakdown(props.expenses, total.value.currency)
    .map((item, index) => ({ ...item, color: colors[index], dot: dots[index] })));
const activity = computed(() => {
    const income = Number(total.value.income);
    const expenses = Number(total.value.expenses);
    const savings = Number(total.value.savings);
    const sum = income + expenses + savings;
    return [
        { label: 'Income', amount: total.value.income, percentage: sum ? income / sum * 100 : 0, color: 'stroke-emerald-500', dot: 'bg-emerald-500' },
        { label: 'Expenses', amount: total.value.expenses, percentage: sum ? expenses / sum * 100 : 0, color: 'stroke-orange-400', dot: 'bg-orange-400' },
        { label: 'Savings', amount: total.value.savings, percentage: sum ? savings / sum * 100 : 0, color: 'stroke-violet-500', dot: 'bg-violet-500' },
    ];
});
const expenseCount = computed(() => props.expenses.filter(item => item.currency === total.value.currency).length);
const transactionCount = computed(() => expenseCount.value
    + props.incomes.filter(item => item.currency === total.value.currency).length
    + props.savings.filter(item => item.currency === total.value.currency).length);
</script>

<template>
    <section aria-labelledby="insights-heading" class="mb-8">
        <h2 id="insights-heading" class="sr-only">Money insights in RSD</h2>
        <div class="grid gap-5 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h3 class="font-semibold text-slate-900">Spending by payer</h3>
                <p class="mt-1 text-xs text-slate-500">Top 5 + others</p>
                <div v-if="spending.length" class="mt-5 flex flex-col items-center gap-5 sm:flex-row xl:flex-col 2xl:flex-row">
                    <DonutChart :segments="spending" label="Expense distribution by payer" :value="String(expenseCount)" caption="expenses" />
                    <ul class="flex w-full min-w-0 flex-col gap-1">
                        <li v-for="item in spending" :key="item.id ?? 'other'">
                            <button type="button" :disabled="item.id === null" :title="`${item.label}: ${item.amount} ${total.currency}`" class="flex w-full items-center justify-between gap-3 rounded-lg px-2 py-2 text-left hover:enabled:bg-slate-50 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="emit('payer', item.id)">
                                <span class="flex min-w-0 items-center gap-2"><span :class="item.dot" class="size-2.5 shrink-0 rounded-full"></span><span class="truncate text-sm text-slate-700">{{ item.label }}</span></span>
                                <span class="shrink-0 text-sm font-semibold text-slate-900 tabular-nums">{{ item.percentage.toFixed(1) }}%</span>
                            </button>

                        </li>
                    </ul>
                </div>
                <p v-else class="py-12 text-center text-sm text-slate-500">Add an expense to see your spending breakdown.</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h3 class="font-semibold text-slate-900">Income, expenses & savings</h3>
                <p class="mt-1 text-xs text-slate-500">Share of total money movement in RSD</p>
                <div v-if="transactionCount" class="mt-5 flex flex-col items-center gap-5 sm:flex-row xl:flex-col 2xl:flex-row">
                    <DonutChart :segments="activity" label="Income, expense and savings shares of total transaction amounts" :value="String(transactionCount)" caption="transactions" />
                    <ul class="flex w-full min-w-0 flex-col gap-5">
                        <li v-for="item in activity" :key="item.label" class="flex items-start justify-between gap-3">
                            <div><p class="flex items-center gap-2 text-sm font-medium text-slate-700"><span :class="item.dot" class="size-2.5 rounded-full"></span>{{ item.label }}</p><p class="mt-2 text-lg font-semibold text-slate-900">{{ item.percentage.toFixed(1) }}%</p></div>
                            <p class="text-right text-sm font-semibold text-slate-800"><MoneyAmount :amount="item.amount" :currency="total.currency" /></p>
                        </li>
                    </ul>
                </div>
                <p v-else class="py-12 text-center text-sm text-slate-500">Add a transaction to see your money movement.</p>
            </article>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { calculateSavingsDisplay } from '../utils/balances.js';
import { formatAmountNumber } from '../utils/formatAmount.js';
import TransactionArrow from './TransactionArrow.vue';

const props = defineProps({
    balances: { type: Array, required: true },
    expenses: { type: Array, required: true },
});
const cardColors = {
    balance: { surface: 'border-indigo-600 bg-indigo-600 text-white', caption: 'text-indigo-100', currency: 'text-indigo-200' },
    income: { surface: 'border-emerald-200 bg-emerald-50 text-emerald-900', caption: 'text-emerald-700', currency: 'text-emerald-600' },
    expense: { surface: 'border-red-200 bg-red-50 text-red-900', caption: 'text-red-700', currency: 'text-red-600' },
    savings: { surface: 'border-violet-200 bg-violet-50 text-violet-900', caption: 'text-violet-700', currency: 'text-violet-600' },
};
const summaries = computed(() => props.balances.map(total => {
    const savings = calculateSavingsDisplay(props.expenses, total.currency);
    return {
        currency: total.currency,
        cards: [
            { kind: 'balance', title: 'Available balance', description: 'After expenses & savings', amount: total.balance, currency: total.currency, negative: total.negative },
            { kind: 'income', title: 'Income', description: 'Received this month', amount: total.income, currency: total.currency },
            { kind: 'expense', title: 'Expenses', description: 'Spending, excluding savings', amount: total.expenses, currency: total.currency },
            { kind: 'savings', title: 'Savings', description: 'Set aside this month', amount: savings.amount, currency: savings.currency, missingCount: savings.missingCount },
        ],
    };
}));
</script>

<template>
    <section aria-labelledby="balance-heading" class="mb-6">
        <h2 id="balance-heading" class="sr-only">Selected month summary by currency</h2>
        <div class="flex flex-col gap-3">
            <div v-for="total in summaries" :key="total.currency" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="card in total.cards" :key="card.kind" :class="cardColors[card.kind].surface" class="flex min-w-0 flex-col gap-4 rounded-xl border p-4 shadow-xs">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold leading-5">{{ card.title }}</p>
                            <p :class="cardColors[card.kind].caption" class="mt-0.5 text-[11px] leading-4">{{ card.description }}</p>
                        </div>
                        <RouterLink v-if="card.kind === 'savings'" :to="{ name: 'transactions', query: { category: 'savings' } }" aria-label="View savings" class="shrink-0 rounded-md p-1 hover:bg-violet-50 focus-visible:outline-2 focus-visible:outline-violet-600">
                            <TransactionArrow savings class="size-4" />
                        </RouterLink>
                        <span v-else class="shrink-0 p-1">
                            <svg v-if="card.kind === 'balance'" aria-hidden="true" class="size-4 text-indigo-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h14a2 2 0 0 1 2 2v12H4V6Zm0 0V4h13v2m-2 6h5v4h-5v-4Z" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            <TransactionArrow v-else :income="card.kind === 'income'" class="size-4" />
                        </span>
                    </div>
                    <p class="mt-auto flex flex-wrap items-baseline justify-end gap-x-2" :class="card.negative ? 'text-rose-200' : ''">
                        <span class="min-w-0 break-all text-2xl font-semibold leading-7 tracking-tight tabular-nums">{{ formatAmountNumber(card.amount, card.currency) }}</span>
                        <span :class="cardColors[card.kind].currency" class="text-[10px] font-medium tracking-wide">{{ card.currency }}</span>
                    </p>
                    <p v-if="card.missingCount" class="text-right text-[11px] text-violet-700">{{ card.missingCount }} {{ card.missingCount === 1 ? 'entry has' : 'entries have' }} no EUR amount</p>
                </div>
            </div>
        </div>
    </section>
</template>

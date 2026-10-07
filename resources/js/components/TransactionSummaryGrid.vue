<script setup>
import { formatAmountNumber } from '../utils/formatAmount.js';
import TransactionArrow from './TransactionArrow.vue';
import TagBadge from './TagBadge.vue';

defineProps({
    groups: { type: Array, required: true },
    kind: { type: String, required: true },
    groupBy: { type: String, default: 'source' },
});
</script>

<template>
    <section :aria-labelledby="`${kind}-${groupBy}-groups-title`" class="min-w-0">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h2 :id="`${kind}-${groupBy}-groups-title`" class="text-base font-semibold text-slate-900">{{ kind === 'income' ? 'Income' : 'Expenses' }} by {{ groupBy === 'tag' ? 'tag' : kind === 'income' ? 'source' : 'payer' }}</h2>
                <p v-if="groupBy === 'tag' && groups.length" class="mt-1 text-xs text-slate-500">Share of total {{ kind === 'income' ? 'income' : 'expenses' }} · Tags may overlap</p>
            </div>
            <RouterLink :to="{ name: 'transactions', query: { category: kind } }" class="rounded-lg px-2 py-1.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 focus-visible:outline-2 focus-visible:outline-indigo-600">View all →</RouterLink>
        </div>
        <p v-if="!groups.length" class="rounded-xl border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500">No {{ groupBy === 'tag' ? 'tagged ' : '' }}{{ kind === 'income' ? 'income' : 'expenses' }} this month.</p>
        <ul v-else class="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
            <li v-for="group in groups" :key="group.key" class="flex min-w-0 flex-col gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2.5 shadow-xs">
                <div class="flex min-w-0 items-center gap-1.5">
                    <h3 v-if="groupBy === 'tag'" class="min-w-0 flex-1"><TagBadge :name="group.label" :icon="group.icon" :color="group.color" /></h3>
                    <template v-else>
                        <TransactionArrow :income="kind === 'income'" class="size-3.5 shrink-0" />
                        <h3 :title="group.label" class="min-w-0 flex-1 truncate text-xs font-medium leading-5 text-slate-600">{{ group.label }}</h3>
                    </template>
                    <span :class="kind === 'income' ? 'text-emerald-700' : 'text-red-700'" class="shrink-0 text-[11px] font-semibold tabular-nums" :title="`Share of ${group.currency} ${kind === 'income' ? 'income' : 'expenses'} this month`">{{ group.percentage.toFixed(1) }}%</span>
                </div>
                <p class="flex flex-wrap items-baseline justify-end gap-x-2 text-base font-semibold leading-5 text-slate-950 tabular-nums">
                    <span class="min-w-0 break-all">{{ formatAmountNumber(group.amount, group.currency) }}</span>
                    <span class="text-[10px] font-medium tracking-wide text-slate-400">{{ group.currency }}</span>
                </p>
                <span class="sr-only">{{ group.count }} {{ group.count === 1 ? 'transaction' : 'transactions' }}</span>
            </li>
        </ul>
    </section>
</template>

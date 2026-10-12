<script setup>
import { computed } from 'vue';
import { formatAmountNumber } from '../utils/formatAmount.js';
import TransactionArrow from './TransactionArrow.vue';
import TagBadge from './TagBadge.vue';
import PayerAvatar from './PayerAvatar.vue';

const props = defineProps({
    groups: { type: Array, required: true },
    kind: { type: String, required: true },
    groupBy: { type: String, default: 'source' },
});
const isPayerGroup = computed(() => props.kind === 'expense' && props.groupBy !== 'tag');
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
                <div :class="isPayerGroup ? 'items-start' : 'items-center'" class="flex min-w-0 gap-1.5">
                    <div v-if="isPayerGroup" class="flex min-w-0 flex-1 flex-col items-start gap-2">
                        <PayerAvatar :name="group.label" :image-url="group.image_url" class="size-12 rounded-sm! text-xl" />
                        <h3 :title="group.label" class="max-w-full truncate text-xs font-medium leading-5 text-slate-600">{{ group.label }}</h3>
                    </div>
                    <h3 v-else-if="groupBy === 'tag'" class="min-w-0 flex-1"><TagBadge :name="group.label" :icon="group.icon" :color="group.color" /></h3>
                    <template v-else>
                        <TransactionArrow :income="kind === 'income'" class="size-3.5 shrink-0" />
                        <h3 :title="group.label" class="min-w-0 flex-1 truncate text-xs font-medium leading-5 text-slate-600">{{ group.label }}</h3>
                    </template>
                    <span :class="kind === 'income' ? 'text-emerald-700' : 'text-red-700'" class="shrink-0 text-[11px] font-semibold tabular-nums" :title="`Share of ${group.currency} ${kind === 'income' ? 'income' : 'expenses'} this month`">{{ group.percentage.toFixed(1) }}%</span>
                </div>
                <p :class="isPayerGroup ? 'text-lg leading-6' : 'text-base leading-5'" class="flex flex-wrap items-baseline justify-end gap-x-2 font-semibold text-slate-950 tabular-nums">
                    <span class="min-w-0 break-all">{{ formatAmountNumber(group.amount, group.currency) }}</span>
                    <span class="text-[10px] font-medium tracking-wide text-slate-400">{{ group.currency }}</span>
                </p>
                <dl class="mt-1 flex flex-col gap-1 border-t border-slate-100 pt-2 text-[11px] leading-5">
                    <div class="flex items-baseline justify-between gap-2">
                        <dt class="text-slate-500">Transactions</dt>
                        <dd class="font-semibold text-slate-700 tabular-nums">{{ group.count.toLocaleString() }}</dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-0.5">
                        <dt class="text-slate-500">Average<span class="sr-only"> transaction value</span></dt>
                        <dd class="ml-auto flex min-w-0 flex-wrap justify-end gap-x-1 font-medium text-slate-700 tabular-nums">
                            <span class="min-w-0 break-all">{{ formatAmountNumber(group.averageAmount, group.currency) }}</span>
                            <span class="text-[10px] text-slate-400">{{ group.currency }}</span>
                        </dd>
                    </div>
                </dl>
            </li>
        </ul>
    </section>
</template>

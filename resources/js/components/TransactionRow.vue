<script setup>
import { computed } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { formatDate } from '../utils/formatDate.js';

import TransactionArrow from './TransactionArrow.vue';
import TagBadge from './TagBadge.vue';
import { formatAmountNumber } from '../utils/formatAmount.js';

const auth = useAuthStore();
const emit = defineEmits(['edit']);
const props = defineProps({
    compact: Boolean,
    transaction: {
        type: Object,
        required: true,
    },
});

const record = computed(() => props.transaction.record);
const isSavings = computed(() => props.transaction.kind === 'savings');
const categoryLabel = computed(() => isSavings.value ? 'Savings' : isIncome.value ? 'Income' : 'Expense');
const isIncome = computed(() => props.transaction.kind === 'income');
const displayDate = computed(() => formatDate(record.value.date, auth.user?.date_format));
</script>

<template>
    <button
        type="button"
        :aria-label="`Edit ${categoryLabel.toLowerCase()}: ${record.description || 'Income'}`"
        class="group grid w-full items-center bg-white px-4 text-left transition-colors hover:bg-slate-50 focus-visible:relative focus-visible:z-10 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600"
        :class="compact ? 'grid-cols-[1.25rem_minmax(0,1fr)_auto] gap-x-3 gap-y-0.5 py-3' : 'grid-cols-[2.25rem_minmax(0,1fr)_auto] gap-x-4 gap-y-1 py-3.5 sm:px-8 xl:grid-cols-[2.25rem_minmax(0,1.4fr)_minmax(0,1fr)_6.5rem_10.5rem] xl:gap-x-5'"
        @click="emit('edit')"
    >
        <template v-if="compact">
            <TransactionArrow :income="isIncome" :savings="isSavings" class="row-span-2 size-5 self-center" />
            <span class="min-w-0 break-words text-sm font-semibold leading-5 text-slate-950">{{ record.description || 'Income' }}</span>
            <span :class="isIncome ? 'text-emerald-700' : 'text-slate-950'" class="self-start text-right text-sm font-semibold leading-5 whitespace-nowrap tabular-nums">{{ isIncome ? '+' : '−' }}{{ formatAmountNumber(record.amount, record.currency) }}</span>
            <time :datetime="record.date" class="col-start-2 text-xs leading-4 text-slate-500">{{ displayDate }}</time>
            <span class="text-right text-[11px] font-medium leading-4 tracking-wide text-slate-500">{{ record.currency }}</span>
            <span v-if="record.tags?.length || (!isIncome && record.receiver.name !== record.description)" class="col-span-2 col-start-2 mt-1 flex min-w-0 flex-wrap items-center gap-1.5">
                <span v-if="!isIncome && record.receiver.name !== record.description" class="break-words text-xs text-slate-600">{{ record.receiver.name }}</span>
                <TagBadge v-for="tag in record.tags" :key="tag.id" :name="tag.name" :icon="tag.icon" :color="tag.color" />
            </span>
        </template>
        <template v-else>
            <span :title="categoryLabel" class="flex size-9 shrink-0 items-center justify-center rounded-xl" :class="isSavings ? 'bg-violet-50' : isIncome ? 'bg-emerald-50' : 'bg-red-50'">
                <TransactionArrow :income="isIncome" :savings="isSavings" class="size-4" />
                <span class="sr-only">{{ categoryLabel }}</span>
            </span>
            <span :title="record.description || 'Income'" class="min-w-0 truncate text-sm font-semibold text-slate-800">{{ record.description || 'Income' }}</span>
            <span :title="record.tags?.map(tag => tag.name).join(', ')" class="hidden min-w-0 items-center gap-1.5 overflow-hidden xl:flex">
                <TagBadge v-if="record.tags?.length" :name="record.tags[0].name" :icon="record.tags[0].icon" :color="record.tags[0].color" class="min-w-0 max-w-full" />
                <span v-if="record.tags?.length > 1" class="shrink-0 rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-500">+{{ record.tags.length - 1 }}</span>
                <span v-if="!record.tags?.length" class="text-xs text-slate-300">—</span>
            </span>
            <time :datetime="record.date" class="hidden text-sm text-slate-500 tabular-nums xl:block">{{ displayDate }}</time>
            <span class="flex items-baseline justify-end gap-2 whitespace-nowrap text-right tabular-nums">
                <span :class="isSavings ? 'text-violet-700' : isIncome ? 'text-emerald-700' : 'text-slate-900'" class="text-sm font-semibold">{{ isIncome ? '+' : '−' }}{{ formatAmountNumber(record.amount, record.currency) }}</span>
                <span class="text-[11px] font-medium tracking-wide text-slate-400">{{ record.currency }}</span>
            </span>
            <span class="col-span-2 col-start-2 flex min-w-0 items-center gap-2 overflow-hidden xl:hidden">
                <time :datetime="record.date" class="shrink-0 text-[11px] text-slate-500 tabular-nums">{{ displayDate }}</time>
                <TagBadge v-for="tag in record.tags?.slice(0, 2)" :key="tag.id" :name="tag.name" :icon="tag.icon" :color="tag.color" class="max-w-28 shrink-0" />
                <span v-if="record.tags?.length > 2" class="shrink-0 text-xs text-slate-500">+{{ record.tags.length - 2 }}</span>
            </span>
        </template>
    </button>
</template>

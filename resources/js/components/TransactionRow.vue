<script setup>
import { computed } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { formatDate } from '../utils/formatDate.js';

import TransactionArrow from './TransactionArrow.vue';
import TagBadge from './TagBadge.vue';
import PayerAvatar from './PayerAvatar.vue';
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
const payerName = computed(() => isIncome.value ? '' : record.value.receiver?.name ?? '');
const primaryLabel = computed(() => payerName.value || record.value.description || categoryLabel.value);
const secondaryLabel = computed(() => payerName.value && record.value.description !== payerName.value ? record.value.description : '');
const transactionLabel = computed(() => [primaryLabel.value, secondaryLabel.value].filter(Boolean).join(' · '));
const paymentLabel = computed(() => isIncome.value ? 'Income received' : ({ card: 'Card', cash: 'Cash', bank_transfer: 'Bank transfer', unspecified: 'Payment not specified' }[record.value.payment_method] ?? 'Payment not specified'));
</script>

<template>
    <button
        type="button"
        :aria-label="`Edit ${categoryLabel.toLowerCase()}: ${transactionLabel}`"
        class="group grid w-full items-center bg-white text-left transition-colors hover:bg-slate-50 focus-visible:relative focus-visible:z-10 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600"
        :class="compact ? 'grid-cols-[1.25rem_minmax(0,1fr)_auto] gap-x-3 gap-y-0.5 px-4 py-3' : 'grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-3 px-5 py-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_7.5rem_9rem] xl:gap-x-5'"
        @click="emit('edit')"
    >
        <template v-if="compact">
            <TransactionArrow :income="isIncome" :savings="isSavings" class="row-span-2 size-5 self-center" />
            <span class="flex min-w-0 flex-col gap-0.5">
                <span class="break-words text-sm font-semibold leading-5 text-slate-950">{{ primaryLabel }}</span>
                <span v-if="secondaryLabel" class="break-words text-xs leading-4 text-slate-600">{{ secondaryLabel }}</span>
            </span>
            <span :class="isIncome ? 'text-emerald-700' : 'text-slate-950'" class="self-start text-right text-sm font-semibold leading-5 whitespace-nowrap tabular-nums">{{ isIncome ? '+' : '−' }}{{ formatAmountNumber(record.amount, record.currency) }}</span>
            <time :datetime="record.date" class="col-start-2 text-xs leading-4 text-slate-500">{{ displayDate }}</time>
            <span class="text-right text-[11px] font-medium leading-4 tracking-wide text-slate-500">{{ record.currency }}</span>
            <span v-if="record.tags?.length" class="col-span-2 col-start-2 mt-1 flex min-w-0 flex-wrap items-center gap-1.5">
                <TagBadge v-for="tag in record.tags" :key="tag.id" :name="tag.name" :icon="tag.icon" :color="tag.color" />
            </span>
        </template>
        <template v-else>
            <span class="flex min-w-0 items-center gap-3.5">
                <PayerAvatar v-if="!isIncome" :name="payerName || primaryLabel" :image-url="record.receiver?.image_url" class="size-12 rounded-sm! text-lg" />
                <span v-else aria-hidden="true" class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50"><TransactionArrow income class="size-5" /></span>
                <span :title="transactionLabel" class="flex min-w-0 flex-1 flex-col gap-1">
                    <span class="truncate text-sm font-semibold text-slate-900">{{ primaryLabel }}</span>
                    <span v-if="secondaryLabel" class="line-clamp-2 text-xs leading-5 text-slate-500">{{ secondaryLabel }}</span>
                    <span v-else class="text-xs text-slate-400">{{ isIncome ? 'Income' : isSavings ? 'Savings contribution' : 'Expense' }}</span>
                </span>
            </span>
            <span :title="record.tags?.map(tag => tag.name).join(', ')" class="hidden min-w-0 flex-wrap items-center gap-1.5 xl:flex">
                <TagBadge v-for="tag in record.tags?.slice(0, 3)" :key="tag.id" :name="tag.name" :icon="tag.icon" :color="tag.color" />
                <span v-if="record.tags?.length > 3" class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-500">+{{ record.tags.length - 3 }}</span>
                <span v-if="!record.tags?.length" class="text-xs text-slate-400">No tags</span>
            </span>
            <span class="hidden flex-col gap-1 xl:flex">
                <time :datetime="record.date" class="text-xs font-medium text-slate-700 tabular-nums">{{ displayDate }}</time>
                <span class="text-xs leading-5 text-slate-400">{{ paymentLabel }}</span>
            </span>
            <span class="flex min-w-0 flex-col items-end gap-1 text-right tabular-nums">
                <span :class="isSavings ? 'text-violet-700' : isIncome ? 'text-emerald-700' : 'text-slate-900'" class="break-all text-sm font-semibold sm:text-base">{{ isIncome ? '+' : '−' }}{{ formatAmountNumber(record.amount, record.currency) }} <span class="text-[10px] font-medium text-slate-400">{{ record.currency }}</span></span>
                <span :class="isSavings ? 'text-violet-600' : isIncome ? 'text-emerald-600' : 'text-slate-400'" class="text-[11px]">{{ categoryLabel }}</span>
                <span v-if="isSavings && record.foreign_amount != null" class="text-[11px] text-violet-600">{{ formatAmountNumber(record.foreign_amount, record.foreign_currency) }} {{ record.foreign_currency }} saved</span>
            </span>
            <span class="col-span-2 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1.5 pl-[3.875rem] xl:hidden">
                <time :datetime="record.date" class="text-[11px] text-slate-500 tabular-nums">{{ displayDate }}</time><span aria-hidden="true" class="text-slate-300">·</span><span class="text-[11px] text-slate-400">{{ paymentLabel }}</span>
                <TagBadge v-for="tag in record.tags?.slice(0, 2)" :key="tag.id" :name="tag.name" :icon="tag.icon" :color="tag.color" class="max-w-full" />
                <span v-if="record.tags?.length > 2" class="shrink-0 text-xs text-slate-500">+{{ record.tags.length - 2 }}</span>
            </span>
        </template>
    </button>
</template>

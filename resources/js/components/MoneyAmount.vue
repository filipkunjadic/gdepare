<script setup>
import { formatAmountNumber } from '../utils/formatAmount.js';

defineProps({
    amount: { type: [String, Number], required: true },
    currency: { type: String, required: true },
    prefix: { type: String, default: '' },
    currencyClass: { type: String, default: 'text-slate-500' },
});
</script>

<template>
    <span class="inline-flex max-w-full flex-col items-end justify-center gap-0.5">
        <span class="break-all leading-snug tabular-nums">{{ prefix }}{{ formatAmountNumber(amount, currency) }}</span>
        <span :class="currencyClass" class="text-xs font-medium tracking-wider">{{ currency }}</span>
    </span>
</template>

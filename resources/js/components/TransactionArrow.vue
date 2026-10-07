<script setup>
defineProps({ income: Boolean, savings: Boolean });
</script>

<template>
    <svg aria-hidden="true" :class="savings ? 'text-violet-600' : income ? 'text-emerald-600' : 'text-red-600'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <path v-if="savings" d="M4 10h16M6 10v10m4-10v10m4-10v10m4-10v10M3 20h18M3 7l9-4 9 4v3H3V7Z" stroke-linecap="round" stroke-linejoin="round" />
        <path v-else :d="income ? 'M7 7 17 17m-9 0h9V8' : 'M7 17 17 7m-9 0h9v9'" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</template>

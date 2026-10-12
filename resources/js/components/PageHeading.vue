<script setup>
defineProps({ title: { type: String, required: true }, description: { type: String, required: true } });
</script>

<template>
    <header class="mb-7 flex flex-col justify-between gap-4 xl:min-h-18 xl:flex-row xl:items-center">
        <div class="flex min-w-0 items-center gap-3 sm:gap-4">
            <span v-if="$slots.icon" aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-2xl border border-indigo-100 bg-white text-indigo-600 shadow-xs sm:size-12"><slot name="icon" /></span>
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">{{ title }}</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">{{ description }}</p>
            </div>
        </div>
        <div v-if="$slots.default" class="flex shrink-0 flex-wrap items-center gap-2"><slot /></div>
    </header>
</template>

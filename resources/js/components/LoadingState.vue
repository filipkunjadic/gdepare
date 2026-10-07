<script setup>
defineProps({
    loading: Boolean,
    error: String,
});

defineEmits(['retry']);
</script>

<template>
    <div :aria-busy="loading">
        <div v-if="loading" role="status" class="mb-6 rounded-2xl border border-slate-200 bg-white p-6">
            <p class="mb-5 flex items-center gap-2 text-sm font-medium text-slate-600"><span aria-hidden="true" class="size-2 rounded-full bg-indigo-500 motion-safe:animate-pulse"></span>Loading your transactions…</p>
            <div aria-hidden="true" class="flex flex-col gap-4 motion-safe:animate-pulse">
                <div class="h-4 w-1/3 rounded bg-slate-100"></div>
                <div class="h-4 w-2/3 rounded bg-slate-100"></div>
                <div class="h-4 w-1/2 rounded bg-slate-100"></div>
            </div>
        </div>

        <div v-else-if="error" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-6">
            <p class="text-red-800">{{ error }}</p>
            <button
                type="button"
                class="mt-4 rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-900 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                @click="$emit('retry')"
            >
                Try again
            </button>
        </div>

        <slot v-else />
    </div>
</template>

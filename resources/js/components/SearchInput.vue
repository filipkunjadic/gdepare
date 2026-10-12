<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, required: true },
    placeholder: { type: String, default: 'Search…' },
    disabled: Boolean,
});
const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <label class="relative block min-w-0">
        <span class="sr-only">{{ label }}</span>
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400"><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 4.5 4.5" stroke-linecap="round" /></svg>
        <input :value="modelValue" type="search" :placeholder="placeholder" :disabled="disabled" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pr-3 pl-10 text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:outline-2 focus:outline-indigo-600 disabled:opacity-50" @input="emit('update:modelValue', $event.target.value)">
    </label>
</template>

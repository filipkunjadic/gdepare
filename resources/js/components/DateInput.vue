<script setup>
import { computed, ref, watch } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { session } from '../session.js';
import { formatDate, parseDate } from '../utils/formatDate.js';

defineOptions({ inheritAttrs: false });
const props = defineProps({ modelValue: { type: String, default: '' }, id: String, name: String, required: Boolean });
const emit = defineEmits(['update:modelValue']);
const auth = useAuthStore();
const format = computed(() => auth.user?.date_format ?? 'd.m.Y');
const draft = ref('');
const input = ref(null);
watch([() => props.modelValue, format], () => {
    draft.value = formatDate(props.modelValue, format.value);
    input.value?.setCustomValidity('');
}, { immediate: true });
function update(event) {
    draft.value = event.target.value;
    const value = parseDate(draft.value, format.value);
    event.target.setCustomValidity(draft.value && !value ? `Enter a valid date (${session.dateFormats[format.value]}).` : '');
    if (value || !draft.value) emit('update:modelValue', value ?? '');
}
function pick(event) {
    draft.value = formatDate(event.target.value, format.value);
    input.value?.setCustomValidity('');
    emit('update:modelValue', event.target.value);
}
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <input :id="id" ref="input" v-bind="$attrs" :name="name" :value="draft" :required="required" :placeholder="session.dateFormats[format]" type="text" autocomplete="off" class="min-w-0 flex-1" @input="update">
        <span class="relative flex size-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 text-slate-600 focus-within:ring-2 focus-within:ring-indigo-600">
            <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 5h16v16H4V5Zm0 5h16M8 2v6m8-6v6" /></svg>
            <input type="date" :value="modelValue" aria-label="Choose date from calendar" class="absolute inset-0 w-full cursor-pointer opacity-0" @input="pick">
        </span>
    </div>
</template>

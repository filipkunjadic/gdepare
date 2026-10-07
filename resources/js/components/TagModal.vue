<script setup>
import { dialogBackdrop } from '../utils/dialogBackdrop.js';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { session } from '../session.js';
import * as tagsApi from '../api/tags.js';
import TagIcon from './TagIcon.vue';
import TagBadge from './TagBadge.vue';

const props = defineProps({ tag: { type: Object, default: null } });
const backdrop = dialogBackdrop(close);
const emit = defineEmits(['close', 'saved']);
const dialog = ref(null);
const saving = ref(false);
const error = ref('');
const form = reactive({ name: props.tag?.name ?? '', icon: props.tag?.icon ?? null, color: props.tag?.color ?? null });
const icons = session.tagIcons ?? {};
const iconSearch = ref('');
const visibleIcons = computed(() => Object.entries(icons).filter(([key, icon]) => `${key} ${icon.label}`.toLowerCase().includes(iconSearch.value.trim().toLowerCase())));
const colors = [
    ['Indigo', '#6366f1'], ['Purple', '#8b5cf6'], ['Pink', '#db2777'], ['Red', '#dc2626'], ['Orange', '#ea580c'],
    ['Amber', '#d97706'], ['Green', '#059669'], ['Teal', '#0d9488'], ['Blue', '#2563eb'], ['Slate', '#475569'],
];
let previousOverflow;

onMounted(() => {
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    dialog.value.showModal();
});
onBeforeUnmount(() => {
    dialog.value?.close();
    document.body.style.overflow = previousOverflow;
});
function close() {
    if (!saving.value) dialog.value.close();
}
async function save() {
    if (saving.value) return;
    saving.value = true;
    error.value = '';
    try {
        const data = { name: form.name.trim(), icon: form.icon, color: form.color?.toLowerCase() ?? null };
        const record = props.tag ? await tagsApi.updateTag(props.tag.id, data) : await tagsApi.createTag(data);
        emit('saved', record);
        dialog.value.close();
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <dialog ref="dialog" aria-labelledby="tag-modal-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/40 backdrop:backdrop-blur-sm" @pointerdown="backdrop.pointerdown" @click="backdrop.click" @close="emit('close')" @cancel="saving && $event.preventDefault()">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 id="tag-modal-title" class="text-lg font-semibold">{{ tag ? 'Edit tag' : 'Create tag' }}</h2>
            <button type="button" aria-label="Close tag form" :disabled="saving" class="rounded-lg px-2 py-1 text-xl text-slate-400 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="close">×</button>
        </div>
        <form class="p-5" :aria-busy="saving" @submit.prevent="save">
            <p v-if="error" role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
            <fieldset :disabled="saving" class="flex min-w-0 flex-col gap-5">
                <label class="text-sm font-medium text-slate-700">Name
                    <input v-model="form.name" name="name" required maxlength="255" autofocus class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-2 focus:outline-indigo-600">
                </label>
                <fieldset class="min-w-0">
                    <legend class="mb-2 text-sm font-medium text-slate-700">Color</legend>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" :aria-pressed="form.color === null" class="rounded-md border border-slate-300 bg-slate-200 px-2 py-1 text-xs text-slate-900 focus-visible:outline-2 focus-visible:outline-indigo-600" :class="form.color === null ? 'ring-2 ring-slate-600 ring-offset-2' : ''" @click="form.color = null">Default</button>
                        <button v-for="[label, value] in colors" :key="value" type="button" :aria-label="label" :title="label" :aria-pressed="form.color?.toLowerCase() === value" :class="form.color?.toLowerCase() === value ? 'ring-2 ring-slate-600 ring-offset-2' : 'hover:ring-2 hover:ring-slate-200'" class="size-7 rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600" @click="form.color = value">
                            <svg aria-hidden="true" viewBox="0 0 28 28" class="size-7"><circle cx="14" cy="14" r="14" :fill="value" /><path v-if="form.color?.toLowerCase() === value" d="m8 14 4 4 8-8" fill="none" stroke="white" stroke-width="2" /></svg>
                        </button>
                    </div>
                    <div class="mt-3 flex items-center gap-3">
                        <label class="flex items-center gap-2 text-xs text-slate-500">Custom color <input :value="form.color || '#e2e8f0'" @input="form.color = $event.target.value" type="color" aria-label="Custom tag color" class="h-8 w-10 cursor-pointer rounded border border-slate-200 bg-white p-0.5"></label>
                        <span class="font-mono text-xs text-slate-500">{{ form.color?.toUpperCase() || 'Default gray' }}</span>
                    </div>
                </fieldset>
                <fieldset class="min-w-0">
                    <legend class="mb-2 text-sm font-medium text-slate-700">Icon</legend>
                    <input v-model="iconSearch" type="search" aria-label="Search icons" placeholder="Search icons…" class="mb-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-2 focus:outline-indigo-600">
                    <div class="grid max-h-64 grid-cols-4 gap-2 overflow-y-auto p-1 sm:grid-cols-6">
                        <button type="button" :aria-pressed="form.icon === null" :class="form.icon === null ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-slate-200 hover:bg-slate-50'" class="flex flex-col items-center justify-center gap-1.5 rounded-lg border px-1 py-2 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="form.icon = null">
                            <TagIcon icon="tag" color="#475569" class="size-5" />
                            <span class="text-[10px] text-slate-500">Default</span>
                        </button>
                        <button v-for="[key, icon] in visibleIcons" :key="key" type="button" :aria-label="icon.label" :aria-pressed="form.icon === key" :class="form.icon === key ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-slate-200 hover:bg-slate-50'" class="flex flex-col items-center justify-center gap-1.5 rounded-lg border px-1 py-2 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="form.icon = key">
                            <TagIcon :icon="key" :color="form.color || '#475569'" class="size-5" />
                            <span class="max-w-full truncate text-[10px] text-slate-500">{{ icon.label }}</span>
                        </button>
                    </div>
                    <p v-if="!visibleIcons.length" role="status" class="mt-2 text-xs text-slate-500">No icons match your search.</p>
                </fieldset>
                <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-3">
                    <span class="text-xs text-slate-400">Preview</span>
                    <TagBadge :name="form.name.trim() || 'Tag name'" :icon="form.icon" :color="form.color" />
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="close">Cancel</button>
                    <button type="submit" :disabled="!form.name.trim()" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-indigo-600 disabled:opacity-50">{{ saving ? 'Saving…' : tag ? 'Save changes' : 'Create tag' }}</button>
                </div>
            </fieldset>
        </form>
    </dialog>
</template>

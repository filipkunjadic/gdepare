<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { dialogBackdrop } from '../utils/dialogBackdrop.js';
import { createPayer, deletePayer, updatePayer } from '../api/payers.js';
import PayerAvatar from './PayerAvatar.vue';
import TagPicker from './TagPicker.vue';

const props = defineProps({ payer: { type: Object, default: null } });
const emit = defineEmits(['close', 'saved', 'deleted']);
const backdrop = dialogBackdrop(close);
const dialog = ref(null);
const fileInput = ref(null);
const name = ref(props.payer?.name ?? '');
const defaultTags = ref(props.payer?.default_tags?.map(tag => tag.name) ?? []);
const image = ref(null);
const previewUrl = ref(null);
const removeImage = ref(false);
const saving = ref(false);
const confirmingDelete = ref(false);
const deleting = ref(false);
const error = ref('');
const imageUrl = computed(() => removeImage.value ? null : previewUrl.value || props.payer?.image_url);
const hasChanges = computed(() => !props.payer || name.value.trim() !== props.payer.name || image.value || (removeImage.value && props.payer.image_url)
    || defaultTags.value.length !== (props.payer.default_tags?.length ?? 0)
    || defaultTags.value.some(name => !props.payer.default_tags?.some(tag => tag.name === name)));
let previousOverflow;

onMounted(() => {
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    dialog.value.showModal();
});
onBeforeUnmount(() => {
    clearPreview();
    dialog.value?.close();
    document.body.style.overflow = previousOverflow;
});

function close() {
    if (!saving.value) dialog.value.close();
}

function clearPreview() {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = null;
    image.value = null;
}

function selectImage(event) {
    const selected = event.target.files?.[0];
    if (!selected) return;
    error.value = '';
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(selected.type) || selected.size > 2 * 1024 * 1024) {
        error.value = 'Choose a JPG, PNG, or WebP image up to 2 MB.';
        event.target.value = '';
        return;
    }
    clearPreview();
    image.value = selected;
    previewUrl.value = URL.createObjectURL(selected);
    removeImage.value = false;
}

function removeSelectedImage() {
    clearPreview();
    fileInput.value.value = '';
    removeImage.value = true;
    error.value = '';
}

async function save() {
    if (saving.value || confirmingDelete.value || !hasChanges.value || !name.value.trim()) return;
    saving.value = true;
    error.value = '';
    try {
        const data = { name: name.value.trim(), image: image.value, removeImage: removeImage.value, defaultTags: defaultTags.value };
        const result = props.payer ? await updatePayer(props.payer.id, data) : await createPayer(data);
        emit('saved', result);
        dialog.value.close();
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        saving.value = false;
    }
}

async function removePayer() {
    if (saving.value || !confirmingDelete.value || !props.payer) return;
    saving.value = true;
    deleting.value = true;
    error.value = '';
    try {
        await deletePayer(props.payer.id);
        emit('deleted', props.payer.id);
        dialog.value.close();
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        saving.value = false;
        deleting.value = false;
    }
}
</script>

<template>
    <dialog ref="dialog" aria-labelledby="payer-modal-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/40 backdrop:backdrop-blur-sm" @pointerdown="backdrop.pointerdown" @click="backdrop.click" @close="emit('close')" @cancel="saving && $event.preventDefault()">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 id="payer-modal-title" class="text-lg font-semibold">{{ payer ? 'Edit payer' : 'Add payer' }}</h2>
            <button type="button" aria-label="Close payer form" :disabled="saving" class="rounded-lg px-2 py-1 text-xl text-slate-400 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="close">×</button>
        </div>
        <form class="p-5" :aria-busy="saving" @submit.prevent="save">
            <p v-if="error && !confirmingDelete" role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
            <fieldset :disabled="saving" class="flex min-w-0 flex-col gap-5">
                <label class="text-sm font-medium text-slate-700">Name
                    <input v-model="name" name="name" required maxlength="255" autofocus class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-2 focus:outline-indigo-600">
                </label>
                <div class="flex flex-col gap-2">
                    <TagPicker id="payer-default-tags" v-model="defaultTags" :initial-tags="payer?.default_tags ?? []" label="Default tags" />
                    <p class="text-xs leading-5 text-slate-500">These tags are added when you choose this payer. You can change them for each transaction.</p>
                </div>
                <div class="flex flex-col items-center gap-3">
                    <PayerAvatar :name="name" :image-url="imageUrl" class="size-[200px] text-5xl" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="payer-image" class="text-sm font-medium text-slate-700">{{ imageUrl ? 'Replace image' : 'Choose an image' }}</label>
                    <input id="payer-image" ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="payer-image-help" class="w-full min-w-0 rounded-lg border border-slate-300 p-2 text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-medium file:text-slate-700 hover:file:bg-slate-200 focus:outline-2 focus:outline-indigo-600" @change="selectImage">
                    <p id="payer-image-help" class="text-xs leading-5 text-slate-500">JPG, PNG, or WebP, up to 2 MB and 4096×4096 pixels. Saved as a 200×200 WebP, cropped to fill the square.</p>
                    <button v-if="imageUrl" type="button" class="self-start rounded-lg py-1 text-sm font-medium text-red-600 hover:text-red-700 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="removeSelectedImage">Remove image</button>
                </div>
                <div v-if="confirmingDelete" class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <p class="break-words text-sm font-medium text-red-900">Delete “{{ payer.name }}”?</p>
                    <p class="mt-1 text-sm leading-6 text-red-700">This cannot be undone. Any linked transactions must be assigned to another payer first.</p>
                    <p v-if="error" role="alert" class="mt-3 text-sm font-medium text-red-800">{{ error }}</p>
                    <div class="mt-4 flex flex-wrap justify-end gap-2">
                        <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 focus-visible:outline-2 focus-visible:outline-red-600" @click="confirmingDelete = false; error = ''">Keep payer</button>
                        <button type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-red-600 disabled:opacity-50" @click="removePayer">{{ deleting ? 'Deleting…' : 'Delete payer' }}</button>
                    </div>
                </div>
                <div v-else class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
                    <button v-if="payer" type="button" class="mr-auto rounded-lg px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-red-600" @click="confirmingDelete = true; error = ''">Delete</button>
                    <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="close">Cancel</button>
                    <button type="submit" :disabled="!hasChanges || !name.trim()" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-indigo-600 disabled:opacity-50">{{ saving ? 'Saving…' : payer ? 'Save changes' : 'Add payer' }}</button>
                </div>
            </fieldset>
        </form>
    </dialog>
</template>

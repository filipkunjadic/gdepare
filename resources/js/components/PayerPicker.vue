<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { getPayers } from '../api/payers.js';
import PayerAvatar from './PayerAvatar.vue';
import SearchSelect from './SearchSelect.vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    initialPayer: { type: Object, default: null },
    id: { type: String, required: true },
    label: { type: String, default: 'Payer' },
    autofocus: Boolean,
});
const emit = defineEmits(['update:modelValue', 'selected', 'loading']);
const records = ref([]);
const loading = ref(true);
const error = ref('');
const payers = computed(() => {
    const byId = new Map([...(props.initialPayer ? [props.initialPayer] : []), ...records.value].map(payer => [payer.id, payer]));
    return [...byId.values()].sort((a, b) => a.name.localeCompare(b.name));
});
const query = computed(() => props.modelValue.trim().toLocaleLowerCase());
const selectedPayer = computed(() => payers.value.find(payer => payer.name.toLocaleLowerCase() === query.value));

watch(selectedPayer, payer => emit('selected', payer ?? null), { immediate: true });
watch(loading, value => emit('loading', value), { immediate: true });

async function refresh() {
    loading.value = true;
    error.value = '';
    try {
        records.value = await getPayers();
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        loading.value = false;
    }
}

onMounted(refresh);
</script>

<template>
    <SearchSelect :id="id" :model-value="modelValue" :options="payers" :label="label" :placeholder="`Search or add ${label.toLocaleLowerCase()}…`" required :autofocus="autofocus" :loading="loading" :error="error" @update:model-value="emit('update:modelValue', $event)" @retry="refresh">
        <template #prefix="{ option }">
            <PayerAvatar :name="option.name" :image-url="option.image_url" class="size-7 rounded-lg text-xs" />
        </template>
        <template #option="{ option }">
            <PayerAvatar :name="option.name" :image-url="option.image_url" class="size-7 rounded-lg text-xs" />
            <span class="min-w-0 flex-1 break-words">{{ option.name }}</span>
        </template>
    </SearchSelect>
</template>

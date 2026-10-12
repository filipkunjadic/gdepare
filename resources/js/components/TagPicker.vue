<script setup>
import { computed, onMounted, ref } from 'vue';
import { getTags } from '../api/tags.js';
import SearchSelect from './SearchSelect.vue';
import TagIcon from './TagIcon.vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    initialTags: { type: Array, default: () => [] },
    id: { type: String, required: true },
    label: { type: String, default: 'Tags' },
});
const emit = defineEmits(['update:modelValue']);
const records = ref([]);
const loading = ref(true);
const error = ref('');
const tags = computed(() => {
    const byName = new Map([...props.initialTags, ...records.value].map(tag => [tag.name, tag]));
    return [...byName.values()].sort((a, b) => a.name.localeCompare(b.name));
});

async function refresh() {
    loading.value = true;
    error.value = '';
    try {
        records.value = await getTags();
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        loading.value = false;
    }
}

onMounted(refresh);
</script>

<template>
    <SearchSelect :id="id" :model-value="modelValue" :options="tags" :label="label" placeholder="Select tags…" multiple :loading="loading" :error="error" @update:model-value="emit('update:modelValue', $event)" @retry="refresh">
        <template #token="{ option }">
            <TagIcon :icon="option.icon || 'tag'" :color="option.color" class="size-3.5 shrink-0" />
            <span :title="option.name" class="min-w-0 truncate">{{ option.name }}</span>
        </template>
        <template #option="{ option }">
            <TagIcon :icon="option.icon || 'tag'" :color="option.color" class="size-4 shrink-0" />
            <span class="min-w-0 flex-1 break-words">{{ option.name }}</span>
        </template>
    </SearchSelect>
</template>

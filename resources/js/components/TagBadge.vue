<script setup>
import { computed } from 'vue';
import TagIcon from './TagIcon.vue';
import { tagColors } from '../utils/tagColors.js';

const props = defineProps({
    name: { type: String, required: true },
    icon: { type: String, default: null },
    color: { type: String, default: null },
});
const colors = computed(() => tagColors(props.color));
</script>

<template>
    <span :title="name" :class="colors.darkText ? 'text-black' : 'text-white'" class="relative isolate inline-flex max-w-full min-w-0 items-center gap-1.5 overflow-hidden rounded-md px-2 py-0.5 text-xs font-medium leading-5">
        <svg aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" preserveAspectRatio="none" viewBox="0 0 1 1"><path d="M0 0h1v1H0Z" :fill="colors.background" /></svg>
        <TagIcon :icon="icon || 'tag'" :color="colors.foreground" class="size-3.5 shrink-0" />
        <span class="min-w-0 truncate">{{ name }}</span>
    </span>
</template>

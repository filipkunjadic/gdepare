<script setup>
import { computed } from 'vue';

const props = defineProps({
    segments: { type: Array, required: true },
    label: { type: String, required: true },
    caption: { type: String, required: true },
    value: { type: String, required: true },
});

const arcs = computed(() => {
    let offset = 0;
    return props.segments.map(segment => {
        const arc = { ...segment, offset };
        offset += segment.percentage;
        return arc;
    });
});
</script>

<template>
    <div class="relative mx-auto size-44 shrink-0 sm:size-48">
        <svg role="img" :aria-label="label" class="size-full" viewBox="0 0 120 120">
            <circle cx="60" cy="60" r="46" fill="none" stroke-width="13" class="stroke-slate-100" />
            <circle v-for="(arc, index) in arcs" :key="index" cx="60" cy="60" r="46" fill="none" stroke-width="13" pathLength="100" :stroke-dasharray="`${arc.percentage} ${100 - arc.percentage}`" :stroke-dashoffset="-arc.offset" transform="rotate(-90 60 60)" :class="arc.color">
                <title>{{ arc.label }}: {{ arc.percentage.toFixed(1) }}%</title>
            </circle>
        </svg>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-1 px-9 text-center">
            <span class="text-2xl font-semibold tracking-tight text-slate-900">{{ value }}</span>
            <span class="text-xs text-slate-500">{{ caption }}</span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { session } from '../session.js';

const props = defineProps({ icon: { type: String, default: null }, color: { type: String, default: '#6366f1' } });
const definition = computed(() => session.tagIcons?.[props.icon]);
const strokeColor = computed(() => /^#[0-9a-f]{6}$/i.test(props.color ?? '') ? props.color : '#6366f1');
</script>

<template>
    <svg v-if="definition" aria-hidden="true" viewBox="0 0 24 24" fill="none" :stroke="strokeColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path :d="definition.path" />
    </svg>
</template>

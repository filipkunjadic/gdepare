<script setup>
defineProps({ name: { type: String, required: true }, imageUrl: { type: String, default: null } });
</script>

<template>
    <span aria-hidden="true" class="flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100 font-semibold text-slate-500">
        <img v-if="imageUrl" :src="imageUrl" alt="" width="200" height="200" class="h-full w-full object-cover">
        <span v-else>{{ name.trim().slice(0, 1).toLocaleUpperCase() }}</span>
    </span>
</template>

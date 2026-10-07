<script setup>
import Header from './Header.vue';
import Footer from './Footer.vue';
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-slate-50 font-sans text-slate-900 antialiased">
        <Header />
        <div class="flex-1"><slot /></div>
        <Footer />
    </div>
</template>

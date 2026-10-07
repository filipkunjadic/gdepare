<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { formatDate, todayDate } from '../utils/formatDate.js';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const menuOpen = ref(false);
const loggingOut = ref(false);
const error = ref('');
const today = computed(() => formatDate(todayDate(), auth.user?.date_format));

async function logout() {
    if (loggingOut.value) return;
    loggingOut.value = true;
    try {
        await auth.logout();
        await router.replace({ name: 'home' });
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        loggingOut.value = false;
    }
}
</script>

<template>
    <div class="min-h-dvh bg-slate-100/70 font-sans text-slate-900 antialiased">
        <div class="flex items-center justify-between bg-slate-950 px-5 py-4 text-white lg:hidden">
            <span class="text-lg font-semibold tracking-tight">Expenses<span class="text-indigo-400">.</span></span>
            <button type="button" :aria-expanded="menuOpen" aria-controls="dashboard-sidebar" class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-indigo-400" @click="menuOpen = !menuOpen">{{ menuOpen ? 'Close menu' : 'Menu' }}</button>
        </div>
        <aside id="dashboard-sidebar" :class="menuOpen ? 'flex' : 'hidden'" class="z-30 flex-col bg-slate-950 text-slate-200 lg:fixed lg:inset-y-0 lg:left-0 lg:flex lg:w-64">
            <RouterLink to="/" class="hidden items-center gap-3 px-6 py-7 lg:flex">
                <span class="flex size-10 items-center justify-center rounded-xl bg-indigo-500 text-white shadow-lg shadow-indigo-500/20">
                    <svg aria-hidden="true" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 7h14a2 2 0 0 1 2 2v10H6a2 2 0 0 1-2-2V7Zm0 0V5a2 2 0 0 1 2-2h11v4M16 12h4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </span>
                <span class="text-xl font-semibold tracking-tight text-white">Expenses<span class="text-indigo-400">.</span></span>
            </RouterLink>
            <nav aria-label="Workspace navigation" class="flex flex-col gap-1 border-b border-slate-800 p-4 pt-2">
                <RouterLink :to="{ name: 'dashboard' }" :class="route.name === 'dashboard' ? 'bg-indigo-500/15 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-400 hover:bg-slate-900 hover:text-white'" class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold" @click="menuOpen = false">
                    <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                    Dashboard
                </RouterLink>
                <RouterLink :to="{ name: 'transactions' }" :class="route.name === 'transactions' ? 'bg-indigo-500/15 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-400 hover:bg-slate-900 hover:text-white'" class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold" @click="menuOpen = false">
                    <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke-linecap="round" /></svg>
                    Transactions
                </RouterLink>
                <RouterLink :to="{ name: 'payers' }" :class="route.name === 'payers' ? 'bg-indigo-500/15 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-400 hover:bg-slate-900 hover:text-white'" class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold" @click="menuOpen = false">
                    <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 4v3" stroke-linecap="round"/></svg>
                    Payers
                </RouterLink>
                <RouterLink :to="{ name: 'tags' }" :class="route.name === 'tags' ? 'bg-indigo-500/15 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-400 hover:bg-slate-900 hover:text-white'" class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold" @click="menuOpen = false">
                    <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 3h8l10 10-8 8L3 11V3Z" stroke-linejoin="round"/><circle cx="7.5" cy="7.5" r="1"/></svg>
                    Tags
                </RouterLink>
                <RouterLink :to="{ name: 'reports' }" :class="route.name === 'reports' ? 'bg-indigo-500/15 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-400 hover:bg-slate-900 hover:text-white'" class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold" @click="menuOpen = false">
                    <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 3h8l4 4v14H6V3Zm8 0v5h4M9 12h6m-6 4h6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Reports
                </RouterLink>
                <RouterLink :to="{ name: 'settings' }" :class="route.name === 'settings' ? 'bg-indigo-500/15 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-400 hover:bg-slate-900 hover:text-white'" class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold" @click="menuOpen = false">
                    <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 7h16M4 17h16M8 4v6m8 4v6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Settings
                </RouterLink>
            </nav>
            <div class="flex-1"></div>
            <div class="border-t border-slate-800 p-5">
                <div class="flex items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-indigo-400/15 text-xs font-semibold text-indigo-200">{{ auth.user?.name.slice(0, 2).toUpperCase() }}</span>
                    <div class="min-w-0"><p class="truncate text-sm font-semibold text-white">{{ auth.user?.name }}</p><p class="truncate text-xs text-slate-400">{{ auth.user?.email }}</p></div>
                </div>
                <button type="button" :disabled="loggingOut" class="mt-4 w-full rounded-lg border border-slate-700 px-3 py-2 text-sm font-medium text-slate-300 transition-colors hover:bg-slate-800 hover:text-white disabled:opacity-50" @click="logout">{{ loggingOut ? 'Signing out…' : 'Sign out' }}</button>
                <p v-if="error" role="alert" class="mt-2 text-sm text-rose-300">{{ error }}</p>
            </div>
        </aside>
        <div class="lg:ml-64">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-5 py-4 sm:px-8">
                <p class="text-sm text-slate-500">Workspace <span class="mx-2 text-slate-300">/</span> <span class="font-medium text-slate-900">{{ route.name === 'settings' ? 'Settings' : route.name === 'reports' ? 'Reports' : route.name === 'tags' ? 'Tags' : route.name === 'payers' ? 'Payers' : route.name === 'transactions' ? 'Transactions' : 'Dashboard' }}</span></p>
                <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600">{{ today }}</span>
            </header>
            <slot />
            <footer class="px-5 py-6 text-xs text-slate-500 sm:px-8">Expenses · Your personal finance workspace</footer>
        </div>
    </div>
</template>

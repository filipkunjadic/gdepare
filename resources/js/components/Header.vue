<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const router = useRouter();
const loggingOut = ref(false);
const error = ref('');

async function logout() {
    loggingOut.value = true;
    error.value = '';
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
    <header class="sticky top-0 z-20 border-b border-slate-200/70 bg-white/90 text-slate-900 backdrop-blur-xl">
        <div class="mx-auto flex max-w-[1440px] flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6 lg:px-8">
            <RouterLink to="/" class="flex items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-indigo-600">
                <span class="flex size-9 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm shadow-indigo-200">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h14a2 2 0 0 1 2 2v10H6a2 2 0 0 1-2-2V7Zm0 0V5a2 2 0 0 1 2-2h11v4M16 12h4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </span>
                <span class="text-xl font-semibold tracking-tight">Expenses<span class="text-indigo-600">.</span></span>
            </RouterLink>

            <div v-if="auth.user" class="ml-auto hidden items-center gap-3 border-r border-slate-200 pr-5 lg:flex">
                <span class="flex size-8 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ auth.user.name.slice(0, 2).toUpperCase() }}</span>
                <span class="text-sm font-medium text-slate-600">{{ auth.user.name }}</span>
            </div>
            <nav aria-label="Main navigation" class="flex flex-wrap items-center gap-1">
                <RouterLink
                    to="/"
                    exact-active-class="bg-indigo-50 text-indigo-700"
                    class="rounded-xl px-3 py-2 text-sm font-medium transition-colors hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                >
                    Home
                </RouterLink>
                <RouterLink
                    v-if="auth.user"
                    :to="{ name: 'dashboard' }"
                    exact-active-class="bg-indigo-50 text-indigo-700"
                    class="rounded-xl px-3 py-2 text-sm font-medium transition-colors hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                >
                    Dashboard
                </RouterLink>
                <RouterLink
                    v-else
                    :to="{ name: 'login' }"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                >
                    Log in
                </RouterLink>
                <button
                    v-if="auth.user"
                    type="button"
                    :disabled="loggingOut"
                    @click="logout"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50"
                >
                    {{ loggingOut ? 'Logging out…' : 'Log out' }}
                </button>
            </nav>
            <p v-if="error" role="alert" class="w-full text-sm text-red-700">{{ error }}</p>
        </div>
    </header>
</template>

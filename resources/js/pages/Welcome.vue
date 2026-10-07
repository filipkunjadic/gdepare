<script setup>
import Layout from "../components/Layout.vue";
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
</script>
<template>
    <Layout>
        <main class="bg-gray-50 px-6 py-16">
            <div class="mx-auto max-w-3xl">
                <h1 class="text-3xl font-semibold text-gray-900">Keep track of your expenses.</h1>
                <p class="mt-4 text-gray-600">A simple place to review your spending.</p>
                <RouterLink
                    v-if="auth.user"
                    :to="{ name: 'dashboard' }"
                    class="mt-8 inline-block rounded-lg bg-blue-600 px-5 py-3 font-medium text-white hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                >
                    Go to dashboard
                </RouterLink>
                <RouterLink
                    v-else
                    :to="{ name: 'login' }"
                    class="mt-8 inline-block rounded-lg bg-blue-600 px-5 py-3 font-medium text-white hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                >
                    Log in
                </RouterLink>
            </div>
        </main>
    </Layout>
</template>

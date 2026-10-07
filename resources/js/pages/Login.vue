<script setup>
import { ref } from 'vue';
import Layout from '../components/Layout.vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const router = useRouter();
const auth = useAuthStore();
const email = ref('');
const password = ref('');
const errors = ref({});
const message = ref('');

const submitting = ref(false);

async function login() {
    if (submitting.value) return;
    submitting.value = true;
    errors.value = {};
    message.value = '';

    try {
        await auth.login({ email: email.value, password: password.value });
        await router.replace({ name: 'dashboard' });
    } catch (error) {
        errors.value = error.errors ?? {};
        message.value = Object.keys(errors.value).length ? '' : error.message;
        password.value = '';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Layout>
        <main class="bg-gray-50 px-6 py-12">
            <div class="mx-auto max-w-md rounded-xl border border-gray-200 bg-white p-8">
                <h1 class="text-2xl font-semibold text-gray-900">Log in</h1>
                <p class="mt-2 text-sm text-gray-600">Enter your email and password to open your dashboard.</p>

                <form class="mt-6 flex flex-col gap-5" @submit.prevent="login">
                    <p v-if="message" role="alert" class="text-sm text-red-700">{{ message }}</p>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            autocomplete="username"
                            required
                            maxlength="255"
                            v-model="email"
                            :aria-invalid="Boolean(errors.email)"
                            :aria-describedby="errors.email ? 'email-error' : undefined"
                            class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-600 focus:outline-2 focus:outline-blue-600"
                        >
                        <p v-if="errors.email" id="email-error" role="alert" class="mt-2 text-sm text-red-700">{{ errors.email[0] }}</p>
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input
                            id="password"
                            v-model="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            :aria-invalid="Boolean(errors.password)"
                            :aria-describedby="errors.password ? 'password-error' : undefined"
                            class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-600 focus:outline-2 focus:outline-blue-600"
                        >
                        <p v-if="errors.password" id="password-error" role="alert" class="mt-2 text-sm text-red-700">{{ errors.password[0] }}</p>
                    </div>
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="rounded-lg bg-blue-600 px-4 py-3 font-medium text-white hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-wait disabled:opacity-50"
                    >
                        {{ submitting ? 'Logging in…' : 'Log in' }}
                    </button>
                </form>
            </div>
        </main>
    </Layout>
</template>

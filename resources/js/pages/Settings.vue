<script setup>
import { computed, reactive, ref } from 'vue';
import DashboardLayout from '../components/DashboardLayout.vue';
import { useAuthStore } from '../stores/auth.js';
import { updateSettings } from '../api/settings.js';
import { session } from '../session.js';
import { formatDate, todayDate } from '../utils/formatDate.js';

const auth = useAuthStore();
const form = reactive({ name: auth.user?.name ?? '', date_format: auth.user?.date_format ?? 'd.m.Y' });
const saving = ref(false);
const error = ref('');
const saved = ref(false);
const preview = computed(() => formatDate(todayDate(), form.date_format));
async function save() {
    if (saving.value) return;
    saving.value = true;
    error.value = '';
    saved.value = false;
    try {
        auth.user = await updateSettings({ name: form.name.trim(), date_format: form.date_format });
        form.name = auth.user.name;
        saved.value = true;
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <DashboardLayout>
        <main class="mx-auto max-w-[1600px] px-4 py-8 sm:px-6 lg:px-8">
            <h1 class="mb-6 text-2xl font-semibold tracking-tight text-slate-950">Settings</h1>
            <form class="max-w-xl rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6" :aria-busy="saving" @submit.prevent="save" @input="saved = false">
                <p v-if="error" role="alert" class="mb-5 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
                <p v-if="saved" role="status" class="mb-5 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">Settings saved.</p>
                <fieldset :disabled="saving" class="flex flex-col gap-5">
                    <label class="text-sm font-medium text-slate-700">Name
                        <input v-model="form.name" name="name" autocomplete="name" required maxlength="255" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-2 focus:outline-indigo-600">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Date format
                        <select v-model="form.date_format" name="date_format" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-2 focus:outline-indigo-600">
                            <option v-for="(label, value) in session.dateFormats" :key="value" :value="value">{{ label }}</option>
                        </select>
                        <span class="mt-2 block text-xs font-normal text-slate-500">Preview: {{ preview }} · Used throughout the app and PDF reports.</span>
                    </label>
                    <div class="flex justify-end border-t border-slate-100 pt-4">
                        <button type="submit" :disabled="!form.name.trim()" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-indigo-600 disabled:opacity-50">{{ saving ? 'Saving…' : 'Save settings' }}</button>
                    </div>
                </fieldset>
            </form>
        </main>
    </DashboardLayout>
</template>

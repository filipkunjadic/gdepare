<script setup>
import { dialogBackdrop } from '../utils/dialogBackdrop.js';
import DateInput from './DateInput.vue';
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useIncomesStore } from '../stores/incomes.js';

const props = defineProps({ income: { type: Object, default: null } });
const backdrop = dialogBackdrop(close);
const emit = defineEmits(['close', 'saved', 'deleted']);
const store = useIncomesStore();
const saving = ref(false);
const confirmingDelete = ref(false);
const deleting = ref(false);
const errors = ref({});
const message = ref('');
const dialog = ref(null);
const today = new Date();
const form = reactive({
    description: props.income?.description ?? '',
    tags: props.income?.tags?.map(tag => tag.name).join(', ') ?? '',
    amount: props.income?.amount ?? '',
    currency: props.income?.currency ?? 'RSD',
    date: props.income?.date ?? `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`,
});
const inputClass = 'mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 transition-colors focus:bg-white text-slate-900 focus:border-emerald-600 focus:outline-2 focus:outline-emerald-600';
let previousOverflow;

onMounted(() => {
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    dialog.value.showModal();
});

onBeforeUnmount(() => {
    dialog.value?.close();
    document.body.style.overflow = previousOverflow;
});

function close() {
    if (!saving.value) dialog.value.close();
}

async function save() {
    if (saving.value || confirmingDelete.value) return;
    saving.value = true;
    errors.value = {};
    message.value = '';

    try {
        const data = {
            ...form,
            tags: [...new Set(form.tags.split(',').map(tag => tag.trim()).filter(Boolean))],
            description: form.description.trim(),
            currency: form.currency.trim().toUpperCase(),
        };
        if (props.income) {
            await store.updateIncome(props.income.id, data);
        } else {
            await store.createIncome(data);
        }
        emit('saved');
        dialog.value.close();
    } catch (error) {
        errors.value = error.errors ?? {};
        message.value = Object.keys(errors.value).length ? 'Please check the following fields.' : error.message;
    } finally {
        saving.value = false;
    }
}
async function remove() {
    if (saving.value || !props.income) return;
    saving.value = true;
    deleting.value = true;
    errors.value = {};
    message.value = '';
    try {
        await store.deleteIncome(props.income.id);
        emit('deleted');
        dialog.value.close();
    } catch (error) {
        message.value = error.message;
    } finally {
        saving.value = false;
        deleting.value = false;
    }
}
</script>

<template>
    <dialog
        ref="dialog"
        aria-labelledby="income-modal-title"
        aria-describedby="income-modal-description"
        class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/40 backdrop:backdrop-blur-sm"
        @pointerdown="backdrop.pointerdown" @click="backdrop.click" @close="emit('close')"
        @cancel="saving && $event.preventDefault()"
    >
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-6 py-6">
            <div>
                <h2 id="income-modal-title" class="text-xl font-semibold tracking-tight">{{ income ? 'Edit income' : 'Add income' }}</h2>
                <p id="income-modal-description" class="mt-1 text-sm text-slate-600">Enter the details of your income.</p>
            </div>
            <button type="button" :disabled="saving" aria-label="Close income form" class="rounded-lg px-3 py-1 text-xl text-slate-500 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-emerald-600" @click="close">&times;</button>
        </div>

        <form class="p-5 sm:p-6" :aria-busy="saving" @submit.prevent="save">
            <div v-if="message" role="alert" class="mb-5 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                <p>{{ message }}</p>
                <ul v-if="Object.keys(errors).length" class="mt-2 list-disc pl-5">
                    <li v-for="(messages, field) in errors" :key="field">{{ messages[0] }}</li>
                </ul>
            </div>
            <fieldset :disabled="saving" class="flex min-w-0 flex-col gap-5">
                <div>
                    <label for="income-description" class="block text-sm font-medium text-slate-700">Description (optional)</label>
                    <input id="income-description" v-model="form.description" name="description" maxlength="255" autofocus placeholder="e.g. Salary or freelance work" :class="inputClass">
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="income-amount" class="block text-sm font-medium text-slate-700">Amount</label>
                        <input id="income-amount" v-model="form.amount" name="amount" type="number" inputmode="decimal" required min="0.01" max="999999999999.99" step="0.01" placeholder="0.00" :class="inputClass">
                    </div>
                    <div>
                        <label for="income-currency" class="block text-sm font-medium text-slate-700">Currency</label>
                        <input id="income-currency" v-model="form.currency" name="currency" required minlength="3" maxlength="3" pattern="[A-Z]{3}" title="Three uppercase letters, e.g. RSD, EUR or USD" :class="inputClass">
                    </div>
                    <div>
                        <label for="income-date" class="block text-sm font-medium text-slate-700">Date</label>
                        <DateInput id="income-date" v-model="form.date" name="date" required :class="inputClass" />
                    </div>
                </div>

                <div>
                    <label for="income-tags" class="block text-sm font-medium text-slate-700">Tags (optional)</label>
                    <input id="income-tags" v-model="form.tags" name="tags" aria-describedby="income-tags-help" placeholder="e.g. Salary, Freelance" :class="inputClass">
                    <p id="income-tags-help" class="mt-2 text-sm text-slate-500">Separate tags with commas. Use the same tags for income and expenses.</p>
                </div>

                <div v-if="confirmingDelete" class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <p class="text-sm font-medium text-red-900">Delete this income permanently?</p>
                    <p class="mt-1 text-sm text-red-700">This cannot be undone.</p>
                    <div class="mt-4 flex flex-wrap justify-end gap-3">
                        <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-red-600" @click="confirmingDelete = false">Keep income</button>
                        <button type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-red-600 disabled:opacity-50" @click="remove">{{ deleting ? 'Deleting…' : 'Delete income' }}</button>
                    </div>
                </div>
                <div v-else class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5">
                    <button v-if="income" type="button" class="mr-auto rounded-lg px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-red-600" @click="confirmingDelete = true">Delete</button>
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-emerald-600" @click="close">Cancel</button>
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 focus-visible:outline-2 focus-visible:outline-emerald-600 disabled:cursor-wait disabled:opacity-50">{{ saving ? 'Saving…' : (income ? 'Save changes' : 'Save income') }}</button>
                </div>
            </fieldset>
        </form>
    </dialog>
</template>

<script setup>
import { dialogBackdrop } from '../utils/dialogBackdrop.js';
import DateInput from './DateInput.vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useExpensesStore } from '../stores/expenses.js';

const props = defineProps({ expense: { type: Object, default: null }, initialCategory: { type: String, default: 'expense' } });
const backdrop = dialogBackdrop(close);
const emit = defineEmits(['close', 'saved', 'deleted']);
const store = useExpensesStore();
const saving = ref(false);
const confirmingDelete = ref(false);
const deleting = ref(false);
const errors = ref({});
const message = ref('');
const dialog = ref(null);
const today = new Date();
const form = reactive({
    category: props.expense?.category ?? props.initialCategory,
    description: props.expense?.description ?? '',
    amount: props.expense?.amount ?? '',
    foreign_amount: props.expense?.foreign_amount ?? '',
    currency: props.expense?.currency ?? 'RSD',
    date: props.expense?.date ?? `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`,
    payment_method: props.expense?.payment_method ?? 'card',
    receiver: props.expense?.receiver.name ?? '',
    tags: props.expense?.tags.map(tag => tag.name).join(', ') ?? '',
});
const categoryLabel = computed(() => form.category === 'savings' ? 'savings' : 'expense');
const inputClass = 'mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 transition-colors focus:bg-white text-slate-900 focus:border-indigo-600 focus:outline-2 focus:outline-indigo-600';
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
            description: form.description.trim(),
            currency: form.category === 'savings' ? 'RSD' : form.currency.trim().toUpperCase(),
            foreign_amount: form.category === 'savings' && form.foreign_amount !== '' ? form.foreign_amount : null,
            foreign_currency: form.category === 'savings' && form.foreign_amount !== '' ? 'EUR' : null,
            receiver: form.receiver.trim(),
            tags: [...new Set(form.tags.split(',').map(tag => tag.trim()).filter(Boolean))],
        };
        if (props.expense) {
            await store.updateExpense(props.expense.id, data);
        } else {
            await store.createExpense(data);
        }
        emit('saved', form.category);
        dialog.value.close();
    } catch (error) {
        errors.value = error.errors ?? {};
        message.value = Object.keys(errors.value).length ? 'Please check the following fields.' : error.message;
    } finally {
        saving.value = false;
    }
}
async function remove() {
    if (saving.value || !props.expense) return;
    saving.value = true;
    deleting.value = true;
    errors.value = {};
    message.value = '';
    try {
        await store.deleteExpense(props.expense.id);
        emit('deleted', props.expense.category ?? 'expense');
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
        aria-labelledby="expense-modal-title"
        aria-describedby="expense-modal-description"
        class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/40 backdrop:backdrop-blur-sm"
        @pointerdown="backdrop.pointerdown" @click="backdrop.click" @close="emit('close')"
        @cancel="saving && $event.preventDefault()"
    >
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-6 py-6">
            <div>
                <h2 id="expense-modal-title" class="text-xl font-semibold tracking-tight">{{ expense ? 'Edit' : 'Add' }} {{ categoryLabel }}</h2>
                <p id="expense-modal-description" class="mt-1 text-sm text-slate-600">{{ form.category === 'savings' ? 'Set money aside without counting it as spending.' : 'Enter the details of your expense.' }}</p>
            </div>
            <button type="button" :disabled="saving" aria-label="Close transaction form" class="rounded-lg px-3 py-1 text-xl text-slate-500 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="close">&times;</button>
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
                    <label for="expense-category" class="block text-sm font-medium text-slate-700">Category</label>
                    <select id="expense-category" v-model="form.category" :class="inputClass">
                        <option value="expense">Expense</option>
                        <option value="savings">Savings</option>
                    </select>
                </div>
                <div>
                    <label for="expense-description" class="block text-sm font-medium text-slate-700">Description</label>
                    <input id="expense-description" v-model="form.description" name="description" required maxlength="255" autofocus :placeholder="form.category === 'savings' ? 'e.g. Emergency fund' : 'e.g. Weekly groceries'" :class="inputClass">
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="expense-amount" class="block text-sm font-medium text-slate-700">{{ form.category === 'savings' ? 'Amount in RSD (required)' : 'Amount' }}</label>
                        <input id="expense-amount" v-model="form.amount" name="amount" type="number" inputmode="decimal" required min="0.01" max="999999999999.99" step="0.01" placeholder="0.00" :class="inputClass">
                    </div>
                    <div v-if="form.category !== 'savings'">
                        <label for="expense-currency" class="block text-sm font-medium text-slate-700">Currency</label>
                        <input id="expense-currency" v-model="form.currency" name="currency" required minlength="3" maxlength="3" pattern="[A-Z]{3}" title="Three uppercase letters, e.g. RSD, EUR or USD" :class="inputClass">
                    </div>
                    <div v-else>
                        <label for="savings-eur-amount" class="block text-sm font-medium text-slate-700">Amount in EUR <span class="font-normal text-slate-500">(optional)</span></label>
                        <input id="savings-eur-amount" v-model="form.foreign_amount" name="foreign_amount" type="number" inputmode="decimal" min="0.01" max="999999999999.99" step="0.01" placeholder="0.00" aria-describedby="savings-amount-help" :class="inputClass">
                    </div>
                    <div>
                        <label for="expense-date" class="block text-sm font-medium text-slate-700">Date</label>
                        <DateInput id="expense-date" v-model="form.date" name="date" required :class="inputClass" />
                    </div>
                    <div>
                        <label for="expense-payment" class="block text-sm font-medium text-slate-700">Payment method</label>
                        <select id="expense-payment" v-model="form.payment_method" name="payment_method" required :class="inputClass">
                            <option value="unspecified">Unspecified</option>
                            <option value="card">Card</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank transfer</option>
                        </select>
                    </div>
                </div>

                <p v-if="form.category === 'savings'" id="savings-amount-help" class="-mt-2 text-sm text-slate-500">RSD is deducted from your balance. Enter the EUR value to show it in the Savings widget.</p>

                <div>
                    <label for="expense-receiver" class="block text-sm font-medium text-slate-700">{{ form.category === 'savings' ? 'Savings destination' : 'Receiver' }}</label>
                    <input id="expense-receiver" v-model="form.receiver" name="receiver" required maxlength="255" :placeholder="form.category === 'savings' ? 'e.g. Savings account or cash reserve' : 'Who did you pay?'" :class="inputClass">
                </div>
                <div>
                    <label for="expense-tags" class="block text-sm font-medium text-slate-700">Tags <span class="font-normal text-slate-500">(optional)</span></label>
                    <input id="expense-tags" v-model="form.tags" name="tags" aria-describedby="expense-tags-help" placeholder="e.g. Food, Household" :class="inputClass">
                    <p id="expense-tags-help" class="mt-2 text-sm text-slate-500">Separate tags with commas.</p>
                </div>

                <div v-if="confirmingDelete" class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <p class="text-sm font-medium text-red-900">Delete this {{ categoryLabel }} permanently?</p>
                    <p class="mt-1 text-sm text-red-700">This cannot be undone.</p>
                    <div class="mt-4 flex flex-wrap justify-end gap-3">
                        <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-red-600" @click="confirmingDelete = false">Keep {{ categoryLabel }}</button>
                        <button type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-red-600 disabled:opacity-50" @click="remove">{{ deleting ? 'Deleting…' : 'Delete ' + categoryLabel }}</button>
                    </div>
                </div>
                <div v-else class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5">
                    <button v-if="expense" type="button" class="mr-auto rounded-lg px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-red-600" @click="confirmingDelete = true">Delete</button>
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="close">Cancel</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-indigo-600 disabled:cursor-wait disabled:opacity-50">{{ saving ? 'Saving…' : (expense ? 'Save changes' : 'Save ' + categoryLabel) }}</button>
                </div>
            </fieldset>
        </form>
    </dialog>
</template>

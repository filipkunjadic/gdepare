<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import MonthSelector from '../components/MonthSelector.vue';
import { usePeriodStore } from '../stores/period.js';
import { recordsForMonth } from '../utils/period.js';
import DashboardLayout from '../components/DashboardLayout.vue';
import LoadingState from '../components/LoadingState.vue';
import TransactionRow from '../components/TransactionRow.vue';
import ExpenseModal from '../components/ExpenseModal.vue';
import IncomeModal from '../components/IncomeModal.vue';
import { useIncomesStore } from '../stores/incomes.js';
import { calculateTagTotals, calculatePayerTotals } from '../utils/balances.js';
import MoneyAmount from '../components/MoneyAmount.vue';
import { useExpensesStore } from '../stores/expenses.js';

const store = useExpensesStore();
const incomeStore = useIncomesStore();
const period = usePeriodStore();
const monthlyExpenses = computed(() => recordsForMonth(store.expenses, period.month));
const monthlyIncomes = computed(() => recordsForMonth(incomeStore.incomes, period.month));
const showingIncomeModal = ref(false);

function refresh() {
    store.loadExpenses(true);
    incomeStore.loadIncomes(true);
}
const showingExpenseModal = ref(false);
const newCategory = ref('expense');
const successMessage = ref('');
const selectedTagId = ref(null);
const selectedCategory = ref(['expense', 'income', 'savings'].includes(useRoute().query.category) ? useRoute().query.category : 'all');
const selectedPayerId = ref(Number(useRoute().query.payer) || null);
const editingExpense = ref(null);
const editingIncome = ref(null);

const tags = computed(() => {
    const tagsById = new Map();

    for (const record of [...store.expenses, ...incomeStore.incomes]) {
        for (const tag of record.tags ?? []) {
            if (!tagsById.has(tag.id)) {
                tagsById.set(tag.id, { ...tag, count: 0 });
            }
            tagsById.get(tag.id).count++;
        }
    }

    return [...tagsById.values()].sort((a, b) => a.name.localeCompare(b.name));
});

const payers = computed(() => {
    const byId = new Map();
    for (const expense of store.expenses) {
        const receiver = expense.receiver;
        if (!byId.has(receiver.id)) byId.set(receiver.id, { ...receiver, count: 0 });
        byId.get(receiver.id).count++;
    }
    return [...byId.values()].sort((a, b) => a.name.localeCompare(b.name));
});
const selectedPayer = computed(() => selectedPayerId.value === null ? null
    : payers.value.find(payer => payer.id === selectedPayerId.value) ?? { id: selectedPayerId.value, name: 'Selected payer' });

const selectedTag = computed(() => tags.value.find(tag => tag.id === selectedTagId.value));
const filterTotals = computed(() => selectedPayer.value
    ? calculatePayerTotals(monthlyExpenses.value, selectedPayer.value.id)
    : selectedTag.value
    ? calculateTagTotals(monthlyExpenses.value, selectedTag.value.id)
    : []);
const transactions = computed(() => {
    const expenses = selectedPayer.value
        ? monthlyExpenses.value.filter(expense => expense.receiver.id === selectedPayer.value.id)
        : selectedTag.value
        ? monthlyExpenses.value.filter(expense => expense.tags.some(tag => tag.id === selectedTag.value.id))
        : monthlyExpenses.value;
    const incomes = selectedPayer.value ? [] : selectedTag.value
        ? monthlyIncomes.value.filter(income => income.tags.some(tag => tag.id === selectedTag.value.id))
        : monthlyIncomes.value;

    return [
        ...expenses.map(record => ({ kind: record.category === 'savings' ? 'savings' : 'expense', record, key: `expense-${record.id}` })),
        ...incomes.map(record => ({ kind: 'income', record, key: `income-${record.id}` })),
    ].filter(transaction => selectedCategory.value === 'all' || transaction.kind === selectedCategory.value)
        .sort((a, b) => b.record.date.localeCompare(a.record.date)
        || b.record.id - a.record.id || a.kind.localeCompare(b.kind));
});

function editTransaction(transaction) {
    successMessage.value = '';
    if (transaction.kind === 'income') {
        editingIncome.value = transaction.record;
        showingIncomeModal.value = true;
    } else {
        editingExpense.value = transaction.record;
        showingExpenseModal.value = true;
    }
}

onMounted(() => {
    store.loadExpenses();
    incomeStore.loadIncomes();
});
</script>

<template>
    <DashboardLayout>

        <main class="text-slate-900">
            <div class="mx-auto max-w-[1600px] px-4 py-8 sm:px-6 lg:px-8 lg:py-8">
                <div class="mb-8 flex flex-wrap items-end justify-between gap-5">
                    <div class="flex flex-wrap items-center gap-4">
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Transactions</h1>
                        <MonthSelector />
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="rounded-xl border border-violet-200 bg-violet-50 px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-violet-600" @click="successMessage = ''; editingExpense = null; newCategory = 'savings'; showingExpenseModal = true">+ Add savings</button>
                        <button type="button" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 transition-colors hover:bg-emerald-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600" @click="successMessage = ''; editingIncome = null; showingIncomeModal = true">
                            + Add income
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-200 transition-colors hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            @click="successMessage = ''; editingExpense = null; newCategory = 'expense'; showingExpenseModal = true"
                        >
                            + Add expense
                        </button>
                        <button
                            type="button"
                            :disabled="store.loading || incomeStore.loading"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-wait disabled:opacity-50"
                            @click="refresh"
                        >
                            Refresh
                        </button>
                    </div>
                </div>

                <p v-if="successMessage" role="status" class="mb-5 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ successMessage }}</p>



                <div class="mb-5 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white p-4">
                    <label class="flex min-w-0 flex-1 flex-col gap-2 text-sm font-medium text-slate-700 sm:min-w-48">
                        Category
                        <select v-model="selectedCategory" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:outline-2 focus:outline-indigo-600">
                            <option value="all">All transactions</option>
                            <option value="expense">Expenses</option>
                            <option value="income">Income</option>
                            <option value="savings">Savings</option>
                        </select>
                    </label>
                    <label class="flex min-w-0 flex-1 flex-col gap-2 text-sm font-medium text-slate-700 sm:min-w-48">
                        Tag
                        <select v-model="selectedTagId" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:outline-2 focus:outline-indigo-600" @change="selectedPayerId = null">
                            <option :value="null">All tags</option>
                            <option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.name }} ({{ tag.count }})</option>
                        </select>
                    </label>
                    <label class="flex min-w-0 flex-1 flex-col gap-2 text-sm font-medium text-slate-700 sm:min-w-48">
                        Payer
                        <select v-model="selectedPayerId" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:outline-2 focus:outline-indigo-600" @change="selectedTagId = null">
                            <option :value="null">All payers</option>
                            <option v-if="selectedPayer && !payers.some(payer => payer.id === selectedPayerId)" :value="selectedPayerId">{{ selectedPayer.name }}</option>
                            <option v-for="payer in payers" :key="payer.id" :value="payer.id">{{ payer.name }} ({{ payer.count }})</option>
                        </select>
                    </label>
                    <button v-if="selectedTag || selectedPayer || selectedCategory !== 'all'" type="button" class="rounded-lg px-3 py-2.5 text-sm font-medium text-indigo-700 hover:bg-indigo-50 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="selectedTagId = null; selectedPayerId = null; selectedCategory = 'all'">Clear filters</button>
                </div>

                <LoadingState
                    :loading="store.loading || incomeStore.loading || (!store.loaded && !store.error) || (!incomeStore.loaded && !incomeStore.error)"
                    :error="store.error || incomeStore.error"
                    @retry="refresh"
                >
                    <div class="min-w-0">


                        <section aria-labelledby="expense-list-title" class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-5 sm:px-6">
                                <h2 id="expense-list-title" class="break-words text-lg font-semibold text-slate-900">{{ selectedPayer ? selectedPayer.name : selectedTag ? selectedTag.name : 'Transactions' }}</h2>
                                <p role="status" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-500">{{ transactions.length }} {{ transactions.length === 1 ? 'transaction' : 'transactions' }}</p>
                            </div>
                            <div v-if="selectedTag || selectedPayer" class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-4 py-3 text-sm sm:px-6">
                                <p class="text-slate-600">{{ selectedPayer ? 'Payer' : 'Tag' }}: <strong class="font-semibold text-slate-900">{{ selectedPayer?.name || selectedTag?.name }}</strong></p>
                                <button type="button" class="rounded-md px-2 py-1 font-medium text-indigo-700 hover:bg-indigo-50 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="selectedTagId = null; selectedPayerId = null">Clear filter ×</button>
                            </div>
                            <div v-if="(selectedTag || selectedPayer) && selectedCategory !== 'savings' && selectedCategory !== 'income'" class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:p-6" role="status">
                                <div v-for="total in filterTotals" :key="total.currency" class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
                                    <div class="flex flex-wrap items-end justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-medium text-indigo-700">{{ selectedPayer ? 'Payer expenses' : 'Tagged expenses' }} · {{ total.currency }}</p>
                                            <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 tabular-nums"><MoneyAmount :amount="total.amount" :currency="total.currency" /></p>
                                        </div>
                                        <p class="text-sm text-slate-600"><strong class="font-semibold text-indigo-700">{{ total.percentage.toFixed(2) }}%</strong> of this month’s {{ total.currency }} expenses</p>
                                    </div>
                                    <svg aria-hidden="true" class="mt-4 h-1.5 w-full overflow-hidden rounded-full" viewBox="0 0 100 6" preserveAspectRatio="none">
                                        <rect width="100" height="6" class="fill-indigo-100" />
                                        <rect :width="total.percentage" height="6" class="fill-indigo-600" />
                                    </svg>
                                    <p class="mt-2 text-xs text-slate-500">Month’s total expenses: <MoneyAmount :amount="total.total" :currency="total.currency" /></p>
                                </div>
                            </div>
                            <div v-if="transactions.length === 0" class="flex flex-col items-center gap-2 px-6 py-16 text-center">
                                <span aria-hidden="true" class="mb-2 flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 3h10v18l-3-2-2 2-2-2-3 2V3Zm3 5h4m-4 4h4" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
                                <h3 class="font-semibold text-slate-900">{{ selectedTag || selectedPayer || selectedCategory !== 'all' ? 'No matching transactions' : 'No transactions this month' }}</h3>
                                <p class="max-w-sm text-sm text-slate-600">Add an income or expense using the buttons above to start tracking your money.</p>
                            </div>
                            <div v-else>
                            <div aria-hidden="true" class="hidden grid-cols-[2.25rem_minmax(0,1.4fr)_minmax(0,1fr)_6.5rem_10.5rem] gap-x-5 border-b border-slate-200 bg-slate-50/80 px-8 py-3 text-[11px] font-semibold tracking-wide text-slate-400 uppercase xl:grid">
                                <span class="sr-only">Type</span><span class="col-start-2">Transaction</span><span>Tags</span><span>Date</span><span class="text-right">Amount</span>
                            </div>
                            <ul class="divide-y divide-slate-100">
                                <li v-for="transaction in transactions" :key="transaction.key">
                                    <TransactionRow :transaction="transaction" @edit="editTransaction(transaction)" />
                                </li>
                            </ul>
                            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/50 px-4 py-3 text-xs text-slate-500 sm:px-6">
                                <span>{{ transactions.length }} transactions · Newest first</span>
                                <span>Click a row to edit</span>
                            </div>
                            </div>
                        </section>
                    </div>
                </LoadingState>

            </div>
        </main>
        <IncomeModal v-if="showingIncomeModal" :income="editingIncome" @close="showingIncomeModal = false" @saved="successMessage = 'Income saved.'; selectedTagId = null; selectedPayerId = null" @deleted="successMessage = 'Income deleted.'" />
        <ExpenseModal v-if="showingExpenseModal" :expense="editingExpense" :initial-category="newCategory" @close="showingExpenseModal = false" @saved="successMessage = $event === 'savings' ? 'Savings saved.' : 'Expense saved.'; selectedTagId = null; selectedPayerId = null" @deleted="successMessage = $event === 'savings' ? 'Savings deleted.' : 'Expense deleted.'" />
    </DashboardLayout>
</template>

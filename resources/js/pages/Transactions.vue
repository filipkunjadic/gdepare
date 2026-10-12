<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import MonthSelector from '../components/MonthSelector.vue';
import { usePeriodStore } from '../stores/period.js';
import { recordsForMonth } from '../utils/period.js';
import DashboardLayout from '../components/DashboardLayout.vue';
import LoadingState from '../components/LoadingState.vue';
import TransactionRow from '../components/TransactionRow.vue';
import ExpenseModal from '../components/ExpenseModal.vue';
import IncomeModal from '../components/IncomeModal.vue';
import AddTransactionButton from '../components/AddTransactionButton.vue';
import { useIncomesStore } from '../stores/incomes.js';
import { calculateBalances } from '../utils/balances.js';
import MoneyAmount from '../components/MoneyAmount.vue';
import { useExpensesStore } from '../stores/expenses.js';
import { getPayers } from '../api/payers.js';
import { getTags } from '../api/tags.js';
import { collectTagUsage, filterTransactions } from '../utils/transactions.js';
import PageHeading from '../components/PageHeading.vue';
import SearchInput from '../components/SearchInput.vue';
import TransactionArrow from '../components/TransactionArrow.vue';

const store = useExpensesStore();
const incomeStore = useIncomesStore();
const period = usePeriodStore();
const route = useRoute();
const directoryPayers = ref([]);
const directoryTags = ref([]);
const directoryError = ref('');
const search = ref('');
const sortOrder = ref('newest');
const monthlyExpenses = computed(() => recordsForMonth(store.expenses, period.month));
const monthlyIncomes = computed(() => recordsForMonth(incomeStore.incomes, period.month));
const showingIncomeModal = ref(false);

function refresh() {
    store.loadExpenses(true);
    incomeStore.loadIncomes(true);
    loadDirectory();
}

async function loadDirectory() {
    directoryError.value = '';
    try {
        [directoryPayers.value, directoryTags.value] = await Promise.all([getPayers(), getTags()]);
    } catch (error) {
        directoryError.value = error.message;
    }
}
const showingExpenseModal = ref(false);
const newCategory = ref('expense');
const successMessage = ref('');
const selectedTagId = ref(null);
const selectedCategory = ref('all');
const selectedPayerId = ref(null);
const editingExpense = ref(null);
const editingIncome = ref(null);

watch(() => route.query, query => {
    selectedCategory.value = ['expense', 'income', 'savings'].includes(query.category) ? query.category : 'all';
    selectedPayerId.value = Number(query.payer) || null;
    selectedTagId.value = Number(query.tag) || null;
    search.value = '';
}, { immediate: true });

const tags = computed(() => {
    const byId = new Map([...store.expenses, ...incomeStore.incomes].flatMap(record => record.tags ?? []).concat(directoryTags.value).map(tag => [tag.id, tag]));
    const usage = collectTagUsage(monthlyExpenses.value, monthlyIncomes.value);
    return [...byId.values()].map(tag => ({ ...tag, count: usage.get(tag.id)?.count ?? 0 })).sort((a, b) => a.name.localeCompare(b.name));
});
const payerById = computed(() => new Map([...store.expenses.map(record => record.receiver), ...directoryPayers.value].map(payer => [payer.id, payer])));
const payers = computed(() => [...payerById.value.values()].sort((a, b) => a.name.localeCompare(b.name)));
const selectedPayer = computed(() => selectedPayerId.value === null ? null
    : payers.value.find(payer => payer.id === selectedPayerId.value) ?? { id: selectedPayerId.value, name: 'Selected payer' });

const selectedTag = computed(() => selectedTagId.value === null ? null
    : tags.value.find(tag => tag.id === selectedTagId.value) ?? { id: selectedTagId.value, name: 'Selected tag' });
const monthlyTransactions = computed(() => [
    ...monthlyExpenses.value.map(record => ({ kind: record.category === 'savings' ? 'savings' : 'expense', record: { ...record, receiver: payerById.value.get(record.receiver.id) ?? record.receiver }, key: `expense-${record.id}` })),
    ...monthlyIncomes.value.map(record => ({ kind: 'income', record, key: `income-${record.id}` })),
]);
const transactions = computed(() => filterTransactions(monthlyTransactions.value, {
    category: selectedCategory.value, payerId: selectedPayerId.value, tagId: selectedTagId.value, search: search.value, order: sortOrder.value,
}));
const categoryCounts = computed(() => {
    const counts = { all: monthlyTransactions.value.length, expense: 0, income: 0, savings: 0 };
    for (const transaction of monthlyTransactions.value) counts[transaction.kind]++;
    return counts;
});
const monthlyBalances = computed(() => calculateBalances(monthlyIncomes.value, monthlyExpenses.value));
const summaryCards = computed(() => [
    { kind: 'income', field: 'income', label: 'Income', color: 'text-emerald-700', background: 'bg-emerald-50' },
    { kind: 'expense', field: 'expenses', label: 'Expenses', color: 'text-orange-700', background: 'bg-orange-50' },
    { kind: 'savings', field: 'savings', label: 'Savings', color: 'text-violet-700', background: 'bg-violet-50' },
].map(card => ({ ...card, count: categoryCounts.value[card.kind], totals: monthlyBalances.value.filter(balance => monthlyTransactions.value.some(transaction => transaction.kind === card.kind && transaction.record.currency === balance.currency)) })));
const hasFilters = computed(() => Boolean(search.value.trim() || selectedTagId.value !== null || selectedPayerId.value !== null || selectedCategory.value !== 'all'));
const filterTotals = computed(() => {
    const matches = calculateBalances([], transactions.value.filter(transaction => transaction.kind === 'expense').map(transaction => transaction.record));
    return matches.map(balance => {
        const total = monthlyBalances.value.find(month => month.currency === balance.currency)?.expenses ?? '0.00';
        return { currency: balance.currency, amount: balance.expenses, percentage: Number(total) ? Number(balance.expenses) / Number(total) * 100 : 0 };
    });
});

function clearFilters() {
    search.value = '';
    selectedCategory.value = 'all';
    selectedTagId.value = null;
    selectedPayerId.value = null;
}

function addExpense(category = 'expense') {
    successMessage.value = '';
    editingExpense.value = null;
    newCategory.value = category;
    showingExpenseModal.value = true;
}

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
    loadDirectory();
});
</script>

<template>
    <DashboardLayout>
        <main class="mx-auto max-w-[1600px] px-4 py-8 text-slate-900 sm:px-6 lg:px-8">
            <PageHeading title="Transactions" description="Review your income, expenses, and savings.">
                <template #icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke-linecap="round" /></svg></template>
                <AddTransactionButton kind="savings" @click="addExpense('savings')" />
                <AddTransactionButton kind="income" @click="successMessage = ''; editingIncome = null; showingIncomeModal = true" />
                <AddTransactionButton kind="expense" @click="addExpense()" />
            </PageHeading>

            <p v-if="successMessage" role="status" class="mb-5 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ successMessage }}</p>
            <div class="mb-5 flex flex-wrap items-center gap-3">
                <MonthSelector />
            </div>
            <p v-if="directoryError" role="alert" class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Some payer images or filter options could not be loaded. <button type="button" class="rounded font-semibold underline focus-visible:outline-2 focus-visible:outline-indigo-600" @click="loadDirectory">Retry</button></p>

            <LoadingState :loading="store.loading || incomeStore.loading || (!store.loaded && !store.error) || (!incomeStore.loaded && !incomeStore.error)" :error="store.error || incomeStore.error" @retry="refresh">
                <section aria-label="Monthly overview" class="mb-5 grid overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs sm:grid-cols-3">
                    <button v-for="card in summaryCards" :key="card.kind" type="button" :aria-pressed="selectedCategory === card.kind" :aria-label="'Filter ' + card.label.toLowerCase()" :class="selectedCategory === card.kind ? card.background : 'hover:bg-slate-50'" class="flex min-w-0 items-start gap-3 border-b border-slate-100 p-5 text-left last:border-0 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:border-r sm:border-b-0" @click="selectedCategory = selectedCategory === card.kind ? 'all' : card.kind">
                        <span :class="card.background" class="flex size-9 shrink-0 items-center justify-center rounded-xl"><TransactionArrow :income="card.kind === 'income'" :savings="card.kind === 'savings'" class="size-4" /></span>
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="flex flex-wrap items-center justify-between gap-2"><span class="text-xs font-medium text-slate-500">{{ card.label }} this month</span><span class="text-[11px] text-slate-400">{{ card.count }} {{ card.count === 1 ? 'entry' : 'entries' }}</span></span>
                            <span v-for="total in card.totals" :key="total.currency" :class="card.color" class="break-words text-xl font-semibold tracking-tight tabular-nums"><MoneyAmount :amount="total[card.field]" :currency="total.currency" /></span>
                            <span v-if="!card.totals.length" class="text-lg font-medium text-slate-400">No entries</span>
                        </span>
                    </button>
                </section>

                <section aria-labelledby="transaction-list-title" class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                    <h2 id="transaction-list-title" class="sr-only">Transaction directory</h2>
                    <div class="flex flex-col gap-4 border-b border-slate-100 p-4 sm:p-5 2xl:flex-row 2xl:items-center 2xl:justify-between">
                        <div role="group" aria-label="Filter transaction category" class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 text-sm">
                            <button v-for="category in [{ value: 'all', label: 'All' }, { value: 'expense', label: 'Expenses' }, { value: 'income', label: 'Income' }, { value: 'savings', label: 'Savings' }]" :key="category.value" type="button" :aria-pressed="selectedCategory === category.value" :class="selectedCategory === category.value ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium focus-visible:outline-2 focus-visible:outline-indigo-600" @click="selectedCategory = category.value">{{ category.label }} <span class="text-[11px] text-slate-400 tabular-nums">{{ categoryCounts[category.value] }}</span></button>
                        </div>
                        <SearchInput v-model="search" label="Search transactions" placeholder="Search payer, description, or tag…" class="w-full 2xl:max-w-sm" />
                    </div>
                    <div class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-4 sm:px-5">
                        <label class="flex min-w-0 flex-1 flex-col gap-1.5 text-xs font-medium text-slate-500 sm:min-w-40 sm:max-w-64">Payer
                            <select v-model="selectedPayerId" class="min-w-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:outline-2 focus:outline-indigo-600">
                                <option :value="null">All payers</option>
                                <option v-if="selectedPayer && !payers.some(payer => payer.id === selectedPayerId)" :value="selectedPayerId">{{ selectedPayer.name }}</option>
                                <option v-for="payer in payers" :key="payer.id" :value="payer.id">{{ payer.name }}</option>
                            </select>
                        </label>
                        <label class="flex min-w-0 flex-1 flex-col gap-1.5 text-xs font-medium text-slate-500 sm:min-w-40 sm:max-w-64">Tag
                            <select v-model="selectedTagId" class="min-w-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:outline-2 focus:outline-indigo-600">
                                <option :value="null">All tags</option>
                                <option v-if="selectedTag && !tags.some(tag => tag.id === selectedTagId)" :value="selectedTagId">{{ selectedTag.name }}</option>
                                <option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.name }} ({{ tag.count }} this month)</option>
                            </select>
                        </label>
                        <button v-if="hasFilters" type="button" class="rounded-lg px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="clearFilters">Clear filters ×</button>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-3">
                        <p role="status" class="text-xs text-slate-500"><strong class="font-semibold text-slate-700">{{ transactions.length }}</strong> of {{ monthlyTransactions.length }} transactions<span v-if="selectedPayer"> · {{ selectedPayer.name }}</span><span v-if="selectedTag"> · {{ selectedTag.name }}</span></p>
                        <label class="flex items-center gap-2 text-xs text-slate-500"><span class="sr-only">Sort transactions</span><select v-model="sortOrder" class="max-w-full rounded-md bg-transparent py-1 text-xs font-medium text-slate-600 focus:outline-2 focus:outline-indigo-600"><option value="newest">Newest first</option><option value="oldest">Oldest first</option></select></label>
                    </div>
                    <div v-if="hasFilters && filterTotals.length" class="flex flex-wrap gap-x-6 gap-y-2 border-b border-indigo-100 bg-indigo-50/40 px-5 py-3 text-xs text-slate-600">
                        <p v-for="total in filterTotals" :key="total.currency">Matching expenses: <strong class="font-semibold text-indigo-800"><MoneyAmount :amount="total.amount" :currency="total.currency" /></strong><span class="text-slate-400"> · </span>{{ total.percentage.toFixed(1) }}% of this month’s {{ total.currency }} expenses</p>
                    </div>
                    <div v-if="!transactions.length" class="flex flex-col items-center gap-3 px-6 py-16 text-center">
                        <span aria-hidden="true" class="flex size-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-500"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 3h10v18l-3-2-2 2-2-2-3 2V3Zm3 5h4m-4 4h4" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
                        <h3 class="font-semibold text-slate-900">{{ hasFilters ? 'No matching transactions' : 'No transactions this month' }}</h3>
                        <p class="max-w-sm text-sm leading-6 text-slate-500">{{ hasFilters ? 'Try another search, adjust your filters, or select a different month.' : 'Record an expense, income, or savings entry to see the details here.' }}</p>
                        <button v-if="hasFilters" type="button" class="rounded-lg px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="clearFilters">Clear filters</button>
                        <AddTransactionButton v-else kind="expense" @click="addExpense()" />
                    </div>
                    <template v-else>
                        <div aria-hidden="true" class="hidden grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_7.5rem_9rem] gap-5 border-b border-slate-100 px-5 py-3 text-[10px] font-semibold tracking-wider text-slate-400 uppercase xl:grid"><span>Payer / description</span><span>Tags</span><span>Date / payment</span><span class="text-right">Amount</span></div>
                        <ul class="divide-y divide-slate-100"><li v-for="transaction in transactions" :key="transaction.key"><TransactionRow :transaction="transaction" @edit="editTransaction(transaction)" /></li></ul>
                        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/50 px-5 py-3 text-xs text-slate-500"><span>{{ transactions.length }} {{ transactions.length === 1 ? 'transaction' : 'transactions' }} shown</span><span>Select a transaction to edit its details</span></div>
                    </template>
                </section>
            </LoadingState>
        </main>
        <IncomeModal v-if="showingIncomeModal" :income="editingIncome" @close="showingIncomeModal = false" @saved="successMessage = 'Income saved.'; clearFilters(); selectedCategory = 'income'" @deleted="successMessage = 'Income deleted.'" />
        <ExpenseModal v-if="showingExpenseModal" :expense="editingExpense" :initial-category="newCategory" @close="showingExpenseModal = false" @saved="successMessage = $event === 'savings' ? 'Savings saved.' : 'Expense saved.'; clearFilters(); selectedCategory = $event" @deleted="successMessage = $event === 'savings' ? 'Savings deleted.' : 'Expense deleted.'" />
    </DashboardLayout>
</template>

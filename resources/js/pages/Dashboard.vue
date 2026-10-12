<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import MonthSelector from '../components/MonthSelector.vue';
import { usePeriodStore } from '../stores/period.js';
import { recordsForMonth } from '../utils/period.js';
import DashboardLayout from '../components/DashboardLayout.vue';
import LoadingState from '../components/LoadingState.vue';
import TransactionSummaryGrid from '../components/TransactionSummaryGrid.vue';
import ExpenseModal from '../components/ExpenseModal.vue';
import IncomeModal from '../components/IncomeModal.vue';
import AddTransactionButton from '../components/AddTransactionButton.vue';
import PageHeading from '../components/PageHeading.vue';
import BalanceSummary from '../components/BalanceSummary.vue';
import FinancialCharts from '../components/FinancialCharts.vue';
import { useIncomesStore } from '../stores/incomes.js';
import { calculateBalances, calculateTransactionGroups } from '../utils/balances.js';
import { useExpensesStore } from '../stores/expenses.js';
import { getPayers } from '../api/payers.js';

const store = useExpensesStore();
const router = useRouter();
const incomeStore = useIncomesStore();
const payers = ref([]);
const payersLoading = ref(true);
const payersError = ref('');
const period = usePeriodStore();
const monthlyExpenses = computed(() => recordsForMonth(store.expenses, period.month));
const monthlyIncomes = computed(() => recordsForMonth(incomeStore.incomes, period.month));
const spendingExpenses = computed(() => monthlyExpenses.value.filter(record => record.category !== 'savings'));
const monthlySavings = computed(() => monthlyExpenses.value.filter(record => record.category === 'savings'));
const showingIncomeModal = ref(false);
const balances = computed(() => {
    const totals = calculateBalances(monthlyIncomes.value, monthlyExpenses.value);
    return totals.length ? totals : [{ currency: 'RSD', income: '0.00', expenses: '0.00', savings: '0.00', balance: '0.00', negative: false }];
});

function refresh() {
    store.loadExpenses(true);
    incomeStore.loadIncomes(true);
    loadPayers();
}

async function loadPayers() {
    payersLoading.value = true;
    payersError.value = '';
    try {
        payers.value = await getPayers();
    } catch (error) {
        payersError.value = error.message;
    } finally {
        payersLoading.value = false;
    }
}
const showingExpenseModal = ref(false);
const newCategory = ref('expense');
const successMessage = ref('');
const editingExpense = ref(null);
const editingIncome = ref(null);

const payerGroups = computed(() => {
    const payerImages = new Map(payers.value.map(payer => [payer.id, payer.image_url]));
    return calculateTransactionGroups(monthlyExpenses.value, 'expense')
        .map(group => ({ ...group, image_url: payerImages.get(group.id) ?? null }));
});
const expenseTagGroups = computed(() => calculateTransactionGroups(monthlyExpenses.value, 'expense', 'tag'));
const incomeTagGroups = computed(() => calculateTransactionGroups(monthlyIncomes.value, 'income', 'tag'));

onMounted(() => {
    store.loadExpenses();
    incomeStore.loadIncomes();
    loadPayers();
});
</script>

<template>
    <DashboardLayout>

        <main class="text-slate-900">
            <div class="mx-auto max-w-[1600px] px-4 py-8 sm:px-6 lg:px-8 lg:py-8">
                <PageHeading title="Dashboard" description="Your income, spending, and savings at a glance.">
                    <template #icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" /></svg></template>
                    <AddTransactionButton kind="savings" @click="successMessage = ''; editingExpense = null; newCategory = 'savings'; showingExpenseModal = true" />
                    <AddTransactionButton kind="income" @click="successMessage = ''; editingIncome = null; showingIncomeModal = true" />
                    <AddTransactionButton kind="expense" @click="successMessage = ''; editingExpense = null; newCategory = 'expense'; showingExpenseModal = true" />
                </PageHeading>

                <p v-if="successMessage" role="status" class="mb-5 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ successMessage }}</p>
                <div class="mb-5 flex flex-wrap items-center gap-3"><MonthSelector /></div>

                <LoadingState
                    :loading="store.loading || incomeStore.loading || (!store.loaded && !store.error) || (!incomeStore.loaded && !incomeStore.error)"
                    :error="store.error || incomeStore.error"
                    @retry="refresh"
                >
                    <BalanceSummary :balances="balances" :expenses="monthlyExpenses" />
                    <FinancialCharts :balances="balances" :expenses="spendingExpenses" :incomes="monthlyIncomes" :savings="monthlySavings" @payer="router.push({ name: 'transactions', query: { payer: $event } })" />
                </LoadingState>

                <LoadingState
                    :loading="store.loading || incomeStore.loading || payersLoading || (!store.loaded && !store.error) || (!incomeStore.loaded && !incomeStore.error)"
                    :error="store.error || incomeStore.error || payersError"
                    @retry="refresh"
                >
                    <div class="flex flex-col gap-7">
                        <TransactionSummaryGrid :groups="payerGroups" kind="expense" />
                        <TransactionSummaryGrid :groups="expenseTagGroups" kind="expense" group-by="tag" />
                        <TransactionSummaryGrid :groups="incomeTagGroups" kind="income" group-by="tag" />
                    </div>
                </LoadingState>

            </div>
        </main>
        <IncomeModal v-if="showingIncomeModal" :income="editingIncome" @close="showingIncomeModal = false" @saved="successMessage = 'Income saved.'" @deleted="successMessage = 'Income deleted.'" />
        <ExpenseModal v-if="showingExpenseModal" :expense="editingExpense" :initial-category="newCategory" @close="showingExpenseModal = false" @saved="successMessage = $event === 'savings' ? 'Savings saved.' : 'Expense saved.'" @deleted="successMessage = $event === 'savings' ? 'Savings deleted.' : 'Expense deleted.'" />
    </DashboardLayout>
</template>

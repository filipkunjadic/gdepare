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
import BalanceSummary from '../components/BalanceSummary.vue';
import FinancialCharts from '../components/FinancialCharts.vue';
import { useIncomesStore } from '../stores/incomes.js';
import { calculateBalances, calculateTransactionGroups } from '../utils/balances.js';
import { useExpensesStore } from '../stores/expenses.js';

const store = useExpensesStore();
const router = useRouter();
const incomeStore = useIncomesStore();
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
}
const showingExpenseModal = ref(false);
const newCategory = ref('expense');
const successMessage = ref('');
const editingExpense = ref(null);
const editingIncome = ref(null);

const payerGroups = computed(() => calculateTransactionGroups(monthlyExpenses.value, 'expense'));
const expenseTagGroups = computed(() => calculateTransactionGroups(monthlyExpenses.value, 'expense', 'tag'));
const incomeTagGroups = computed(() => calculateTransactionGroups(monthlyIncomes.value, 'income', 'tag'));

onMounted(() => {
    store.loadExpenses();
    incomeStore.loadIncomes();
});
</script>

<template>
    <DashboardLayout>

        <main class="text-slate-900">
            <div class="mx-auto max-w-[1600px] px-4 py-8 sm:px-6 lg:px-8 lg:py-8">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-5">
                    <div class="flex flex-wrap items-center gap-4">
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Dashboard</h1>
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

                <LoadingState
                    :loading="store.loading || incomeStore.loading || (!store.loaded && !store.error) || (!incomeStore.loaded && !incomeStore.error)"
                    :error="store.error || incomeStore.error"
                    @retry="refresh"
                >
                    <BalanceSummary :balances="balances" :expenses="monthlyExpenses" />
                    <FinancialCharts :balances="balances" :expenses="spendingExpenses" :incomes="monthlyIncomes" :savings="monthlySavings" @payer="router.push({ name: 'transactions', query: { payer: $event } })" />
                </LoadingState>

                <LoadingState
                    :loading="store.loading || incomeStore.loading || (!store.loaded && !store.error) || (!incomeStore.loaded && !incomeStore.error)"
                    :error="store.error || incomeStore.error"
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

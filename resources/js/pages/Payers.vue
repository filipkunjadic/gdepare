<script setup>
import { computed, onMounted, ref } from 'vue';
import DashboardLayout from '../components/DashboardLayout.vue';
import MonthSelector from '../components/MonthSelector.vue';
import LoadingState from '../components/LoadingState.vue';
import MoneyAmount from '../components/MoneyAmount.vue';
import { getPayers } from '../api/payers.js';
import { useExpensesStore } from '../stores/expenses.js';
import { usePeriodStore } from '../stores/period.js';
import { recordsForMonth } from '../utils/period.js';
import { calculateTransactionGroups } from '../utils/balances.js';

const expenses = useExpensesStore();
const period = usePeriodStore();
const payers = ref([]);
const search = ref('');
const loading = ref(true);
const error = ref('');
const totals = computed(() => {
    const byPayer = new Map();
    for (const group of calculateTransactionGroups(recordsForMonth(expenses.expenses, period.month), 'expense')) {
        if (!byPayer.has(group.id)) byPayer.set(group.id, []);
        byPayer.get(group.id).push(group);
    }
    return byPayer;
});
const visiblePayers = computed(() => payers.value.filter(payer => payer.name.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase())));

async function refresh() {
    loading.value = true;
    error.value = '';
    try {
        const [records] = await Promise.all([getPayers(), expenses.loadExpenses(true)]);
        payers.value = records;
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        loading.value = false;
    }
}

onMounted(refresh);
</script>

<template>
    <DashboardLayout>
        <main class="mx-auto max-w-[1600px] px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-4">
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Payers</h1>
                    <MonthSelector />
                </div>
                <button type="button" :disabled="loading || expenses.loading" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-indigo-600 disabled:opacity-50" @click="refresh">Refresh</button>
            </div>
            <label class="mb-5 block max-w-sm">
                <span class="sr-only">Search payers</span>
                <input v-model="search" type="search" placeholder="Search payers…" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm focus:outline-2 focus:outline-indigo-600">
            </label>
            <LoadingState :loading="loading || expenses.loading" :error="error || expenses.error" @retry="refresh">
                <section aria-label="All payers" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-medium text-slate-500 sm:px-5">
                        <span>{{ visiblePayers.length }} {{ visiblePayers.length === 1 ? 'payer' : 'payers' }}</span>
                        <span>Expenses this month</span>
                    </div>
                    <p v-if="!visiblePayers.length" class="px-5 py-12 text-center text-sm text-slate-500">{{ search.trim() ? 'No payers match your search.' : 'No payers yet. Add an expense to create one.' }}</p>
                    <ul v-else class="divide-y divide-slate-100">
                        <li v-for="payer in visiblePayers" :key="payer.id">
                            <RouterLink :to="{ name: 'transactions', query: { payer: payer.id, category: 'expense' } }" class="flex items-center justify-between gap-4 px-4 py-3 hover:bg-slate-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:px-5">
                                <span class="min-w-0 break-words text-sm font-semibold text-slate-800">{{ payer.name }}</span>
                                <span class="flex shrink-0 items-center gap-4">
                                    <span v-if="totals.has(payer.id)" class="flex flex-col gap-2">
                                        <span v-for="total in totals.get(payer.id)" :key="total.currency" class="flex items-center justify-end gap-4">
                                            <span class="text-xs text-slate-500 tabular-nums">{{ total.percentage.toFixed(1) }}%</span>
                                            <span class="text-right text-sm font-semibold text-slate-900"><MoneyAmount :amount="total.amount" :currency="total.currency" /></span>
                                        </span>
                                    </span>
                                    <span v-else class="text-xs text-slate-400">No expenses</span>
                                    <span aria-hidden="true" class="text-slate-400">→</span>
                                </span>
                            </RouterLink>
                        </li>
                    </ul>
                </section>
            </LoadingState>
        </main>
    </DashboardLayout>
</template>

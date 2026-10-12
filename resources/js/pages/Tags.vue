<script setup>
import { computed, onMounted, ref } from 'vue';
import DashboardLayout from '../components/DashboardLayout.vue';
import LoadingState from '../components/LoadingState.vue';
import TagModal from '../components/TagModal.vue';
import TagBadge from '../components/TagBadge.vue';
import * as tagsApi from '../api/tags.js';
import { useExpensesStore } from '../stores/expenses.js';
import { useIncomesStore } from '../stores/incomes.js';
import { useAuthStore } from '../stores/auth.js';
import { collectTagUsage } from '../utils/transactions.js';
import { formatDate } from '../utils/formatDate.js';
import PageHeading from '../components/PageHeading.vue';
import SearchInput from '../components/SearchInput.vue';
import TagIcon from '../components/TagIcon.vue';

const tags = ref([]);
const search = ref('');
const usageFilter = ref('all');
const showingModal = ref(false);
const editingTag = ref(null);
const deletingId = ref(null);
const loading = ref(true);
const saving = ref(false);
const loadError = ref('');
const actionError = ref('');
const message = ref('');
const expenseStore = useExpensesStore();
const incomeStore = useIncomesStore();
const auth = useAuthStore();
const usage = computed(() => collectTagUsage(expenseStore.expenses, incomeStore.incomes));
const activityError = computed(() => expenseStore.error || incomeStore.error);
const activityReady = computed(() => expenseStore.loaded && incomeStore.loaded && !expenseStore.loading && !incomeStore.loading && !activityError.value);
const visibleTags = computed(() => tags.value.filter(tag => {
    if (!tag.name.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase())) return false;
    if (!activityReady.value || usageFilter.value === 'all') return true;
    return usageFilter.value === 'used' ? usage.value.has(tag.id) : !usage.value.has(tag.id);
}));
const buttonClass = 'rounded-lg px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-indigo-600 disabled:opacity-50';

function loadActivity(force = false) {
    expenseStore.loadExpenses(force);
    incomeStore.loadIncomes(force);
}

async function refresh() {
    if (saving.value) return;
    loading.value = true;
    loadError.value = '';
    deletingId.value = null;
    loadActivity(true);
    try {
        tags.value = await tagsApi.getTags();
    } catch (error) {
        loadError.value = error.message;
    } finally {
        loading.value = false;
    }
}

function edit(tag) {
    editingTag.value = tag;
    showingModal.value = true;
    deletingId.value = null;
    actionError.value = '';
}

function saved(record) {
    const existed = tags.value.some(tag => tag.id === record.id);
    tags.value = (existed ? tags.value.map(tag => tag.id === record.id ? record : tag) : [...tags.value, record])
        .sort((a, b) => a.name.localeCompare(b.name));
    expenseStore.reset();
    incomeStore.reset();
    loadActivity();
    search.value = '';
    message.value = existed ? 'Tag updated.' : 'Tag created.';
}

async function remove(tag) {
    if (saving.value) return;
    saving.value = true;
    actionError.value = '';
    message.value = '';
    try {
        await tagsApi.deleteTag(tag.id);
        tags.value = tags.value.filter(record => record.id !== tag.id);
        expenseStore.reset();
        incomeStore.reset();
        loadActivity();
        deletingId.value = null;
        message.value = 'Tag deleted. Your transactions were kept.';
    } catch (error) {
        actionError.value = error.message;
    } finally {
        saving.value = false;
    }
}

onMounted(refresh);
</script>

<template>
    <DashboardLayout>
        <main class="mx-auto max-w-[1600px] px-4 py-8 sm:px-6 lg:px-8">
            <PageHeading title="Tags" description="Organize transactions with labels that are easy to find.">
                <template #icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 3h8l10 10-8 8L3 11V3Z" stroke-linejoin="round" /><circle cx="7.5" cy="7.5" r="1" /></svg></template>
                <button type="button" :disabled="loading || saving" class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl border border-indigo-600 bg-indigo-600 px-4 text-sm leading-5 font-semibold whitespace-nowrap text-white shadow-xs transition-colors hover:border-indigo-700 hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50" @click="edit(null)"><svg aria-hidden="true" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg> Add tag</button>
            </PageHeading>
            <p v-if="message" role="status" class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ message }}</p>
            <p v-if="actionError" role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ actionError }}</p>
            <div class="mb-5 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs sm:p-5 xl:flex-row xl:items-center xl:justify-between">
                <div role="group" aria-label="Filter tags by usage" class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 text-sm">
                    <button v-for="filter in [{ value: 'all', label: 'All tags' }, { value: 'used', label: 'In use' }, { value: 'unused', label: 'Unused' }]" :key="filter.value" type="button" :disabled="saving || (filter.value !== 'all' && !activityReady)" :aria-pressed="usageFilter === filter.value" :class="usageFilter === filter.value ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'" class="rounded-lg px-3 py-2 font-medium focus-visible:outline-2 focus-visible:outline-indigo-600 disabled:opacity-50" @click="usageFilter = filter.value">{{ filter.label }}</button>
                </div>
                <SearchInput v-model="search" label="Search tags" placeholder="Find a tag…" :disabled="saving" class="w-full xl:max-w-sm" />
            </div>
            <p v-if="activityError" role="alert" class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Transaction activity could not be loaded. <button type="button" class="rounded font-semibold underline focus-visible:outline-2 focus-visible:outline-indigo-600" @click="loadActivity(true)">Retry activity</button></p>
            <LoadingState :loading="loading" :error="loadError" label="Loading your tags…" @retry="refresh">
                <section aria-label="Tag library" :aria-busy="saving">
                    <div class="mb-4 flex items-center justify-between gap-3 px-1 text-xs text-slate-500"><p role="status">{{ visibleTags.length }} of {{ tags.length }} tags</p><span>All-time usage · Name A–Z</span></div>
                    <div v-if="!visibleTags.length" class="flex flex-col items-center gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-16 text-center">
                        <span aria-hidden="true" class="flex size-14 items-center justify-center rounded-2xl bg-indigo-50"><TagIcon icon="tag" class="size-6" /></span>
                        <h2 class="font-semibold text-slate-900">{{ tags.length ? 'No tags match these filters' : 'A little organization goes a long way' }}</h2>
                        <p class="max-w-sm text-sm leading-6 text-slate-500">{{ tags.length ? 'Try another name or view all of your tags.' : 'Create labels such as Groceries, Home, or Travel to make transactions easier to find.' }}</p>
                        <button v-if="tags.length" type="button" :disabled="saving" :class="buttonClass" class="text-indigo-700 hover:bg-indigo-50" @click="search = ''; usageFilter = 'all'">Clear filters</button>
                        <button v-else type="button" :disabled="saving" :class="buttonClass" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="edit(null)">+ Create your first tag</button>
                    </div>
                    <ul v-else class="grid items-start gap-4 md:grid-cols-2 2xl:grid-cols-3">
                        <li v-for="tag in visibleTags" :key="tag.id" class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs transition-shadow hover:shadow-md">
                            <div class="p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <button type="button" :disabled="saving" :aria-label="`Edit ${tag.name}`" class="flex min-w-0 items-center gap-3 rounded-lg text-left focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-600" @click="edit(tag)">
                                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-slate-100 bg-slate-50"><TagIcon :icon="tag.icon || 'tag'" :color="tag.color" class="size-6" /></span>
                                        <span class="min-w-0"><span class="block break-words text-base font-semibold text-slate-900">{{ tag.name }}</span><span class="mt-1 block text-xs text-slate-500">{{ activityReady ? (usage.get(tag.id)?.count ? `${usage.get(tag.id).count} ${usage.get(tag.id).count === 1 ? 'transaction' : 'transactions'}` : 'Not used yet') : activityError ? 'Activity unavailable' : 'Loading activity…' }}</span></span>
                                    </button>
                                    <button type="button" :disabled="saving" :aria-label="`Delete ${tag.name}`" title="Delete tag" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 focus-visible:outline-2 focus-visible:outline-indigo-600 disabled:opacity-50" @click="deletingId = tag.id; actionError = ''"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="size-4"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 10v6m4-6v6" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
                                </div>
                                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-3">
                                    <span class="text-xs text-slate-500">Transaction label</span><TagBadge :name="tag.name" :icon="tag.icon" :color="tag.color" />
                                </div>
                                <p class="mt-4 text-xs text-slate-500">{{ activityReady ? (usage.get(tag.id)?.lastDate ? `Last used ${formatDate(usage.get(tag.id).lastDate, auth.user?.date_format)}` : 'Ready to add to an income, expense, or savings entry.') : 'Usage includes income, expenses, and savings.' }}</p>
                            </div>
                            <div class="flex items-center justify-between gap-2 border-t border-slate-100 px-5 py-3">
                                <RouterLink :to="{ name: 'transactions', query: { tag: tag.id } }" :aria-label="`View transactions tagged ${tag.name}`" class="rounded-lg py-2 text-xs font-semibold text-slate-600 hover:text-indigo-700 focus-visible:outline-2 focus-visible:outline-indigo-600">View transactions <span aria-hidden="true">↗</span></RouterLink>
                                <button type="button" :disabled="saving" :class="buttonClass" class="border border-indigo-100 bg-indigo-50/50 text-indigo-700 hover:bg-indigo-50" @click="edit(tag)">Edit tag</button>
                            </div>
                            <div v-if="deletingId === tag.id" class="mx-4 mb-4 rounded-xl border border-red-200 bg-red-50 p-4">
                                <p class="break-words text-sm font-medium text-red-900">Delete “{{ tag.name }}”?</p>
                                <p class="mt-1 text-sm leading-5 text-red-700">This removes the tag from transactions and payer defaults. Your transactions will be kept.</p>
                                <div class="mt-3 flex justify-end gap-2">
                                    <button type="button" :disabled="saving" :class="buttonClass" class="bg-white text-slate-700 hover:bg-slate-100" @click="deletingId = null">Cancel</button>
                                    <button type="button" :disabled="saving" :class="buttonClass" class="bg-red-600 text-white hover:bg-red-700" @click="remove(tag)">{{ saving ? 'Deleting…' : 'Delete tag' }}</button>
                                </div>
                            </div>
                        </li>
                    </ul>
                </section>
            </LoadingState>
        </main>
        <TagModal v-if="showingModal" :tag="editingTag" @close="showingModal = false" @saved="saved" />
    </DashboardLayout>
</template>

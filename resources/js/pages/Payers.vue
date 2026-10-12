<script setup>
import { computed, onMounted, ref } from 'vue';
import DashboardLayout from '../components/DashboardLayout.vue';
import LoadingState from '../components/LoadingState.vue';
import PayerAvatar from '../components/PayerAvatar.vue';
import PayerModal from '../components/PayerModal.vue';
import { getPayers } from '../api/payers.js';
import { useExpensesStore } from '../stores/expenses.js';
import PageHeading from '../components/PageHeading.vue';
import SearchInput from '../components/SearchInput.vue';
import TagBadge from '../components/TagBadge.vue';

const expenses = useExpensesStore();
const payers = ref([]);
const search = ref('');
const tagFilter = ref('all');
const loading = ref(true);
const error = ref('');
const editingPayer = ref(null);
const showingModal = ref(false);
const message = ref('');
const visiblePayers = computed(() => payers.value.filter(payer => {
    const hasDefaults = Boolean(payer.default_tags?.length);
    if (tagFilter.value === 'with' && !hasDefaults) return false;
    if (tagFilter.value === 'without' && hasDefaults) return false;
    return [payer.name, ...(payer.default_tags ?? []).map(tag => tag.name)].join(' ').toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase());
}));

async function refresh() {
    loading.value = true;
    error.value = '';
    try {
        payers.value = await getPayers();
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        loading.value = false;
    }
}

onMounted(refresh);

function openPayerModal(payer = null) {
    editingPayer.value = payer;
    showingModal.value = true;
    message.value = '';
}

function saved(payer) {
    payers.value = [...payers.value.filter(record => record.id !== payer.id), payer]
        .sort((a, b) => a.name.localeCompare(b.name));
    expenses.reset();
    search.value = '';
    tagFilter.value = 'all';
    message.value = editingPayer.value ? 'Payer updated.' : 'Payer added.';
}

function deleted(id) {
    payers.value = payers.value.filter(payer => payer.id !== id);
    expenses.reset();
    message.value = 'Payer deleted.';
}
</script>

<template>
    <DashboardLayout>
        <main class="mx-auto max-w-[1600px] px-4 py-8 sm:px-6 lg:px-8">
            <PageHeading title="Payers" description="Manage who you pay, their images, and default tags.">
                <template #icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 10h16v11H4V10ZM3 10l2-7h14l2 7M9 21v-7h6v7M3 10c0 3 4 3 4 0 0 3 5 3 5 0 0 3 5 3 5 0 0 3 4 3 4 0" stroke-linecap="round" stroke-linejoin="round" /></svg></template>
                <button type="button" class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl border border-indigo-600 bg-indigo-600 px-4 text-sm leading-5 font-semibold whitespace-nowrap text-white shadow-xs transition-colors hover:border-indigo-700 hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600" @click="openPayerModal()"><svg aria-hidden="true" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg> Add payer</button>
            </PageHeading>
            <p v-if="message" role="status" class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ message }}</p>
            <section aria-label="Payer directory" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                <div class="flex flex-col gap-4 border-b border-slate-100 p-4 sm:p-5 xl:flex-row xl:items-center xl:justify-between">
                    <div role="group" aria-label="Filter by default tags" class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 text-sm">
                        <button v-for="filter in [{ value: 'all', label: 'All payers' }, { value: 'with', label: 'With default tags' }, { value: 'without', label: 'No defaults' }]" :key="filter.value" type="button" :aria-pressed="tagFilter === filter.value" :class="tagFilter === filter.value ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'" class="rounded-lg px-3 py-2 font-medium focus-visible:outline-2 focus-visible:outline-indigo-600" @click="tagFilter = filter.value">{{ filter.label }}</button>
                    </div>
                    <SearchInput v-model="search" label="Search payers and default tags" placeholder="Search payers or tags…" class="w-full xl:max-w-sm" />
                </div>
                <LoadingState :loading="loading" :error="error" label="Loading your payers…" @retry="refresh">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/60 px-5 py-3">
                        <p role="status" class="text-xs font-medium text-slate-500">{{ visiblePayers.length }} of {{ payers.length }} payers</p>
                        <span class="text-xs text-slate-400">Name A–Z</span>
                    </div>
                    <div v-if="!visiblePayers.length" class="flex flex-col items-center gap-3 px-5 py-16 text-center">
                        <span aria-hidden="true" class="flex size-14 items-center justify-center rounded-2xl bg-indigo-50 text-2xl text-indigo-500">↗</span>
                        <h2 class="font-semibold text-slate-900">{{ payers.length ? 'No matching payers' : 'Your payers will appear here' }}</h2>
                        <p class="max-w-sm text-sm leading-6 text-slate-500">{{ payers.length ? 'Try a different name, tag, or default-tag filter.' : 'Add your first payer with a name, image, and default tags for future transactions.' }}</p>
                        <button v-if="payers.length" type="button" class="rounded-lg px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50" @click="search = ''; tagFilter = 'all'">Clear filters</button>
                        <button v-else type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="openPayerModal()">Add payer</button>
                    </div>
                    <ul v-else class="divide-y divide-slate-100">
                        <li v-for="payer in visiblePayers" :key="payer.id">
                            <div class="flex flex-col gap-3 px-5 py-5 transition-colors hover:bg-slate-50/70 sm:flex-row sm:items-center sm:gap-5">
                                <button type="button" :aria-label="`Edit ${payer.name}`" class="grid min-w-0 flex-1 gap-4 rounded-lg text-left focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-600 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)] xl:items-center" @click="openPayerModal(payer)">
                                    <span class="flex min-w-0 items-center gap-3.5">
                                        <PayerAvatar :name="payer.name" :image-url="payer.image_url" class="size-12 rounded-sm! text-lg" />
                                        <span class="min-w-0"><span class="block break-words text-sm font-semibold text-slate-900">{{ payer.name }}</span><span class="mt-1 block text-xs text-slate-500">Edit name, image & defaults</span></span>
                                    </span>
                                    <span class="flex min-w-0 flex-col gap-2">
                                        <span class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase">Default tags</span>
                                        <span v-if="payer.default_tags?.length" class="flex min-w-0 flex-wrap gap-1.5"><TagBadge v-for="tag in payer.default_tags" :key="tag.id" :name="tag.name" :icon="tag.icon" :color="tag.color" /></span>
                                        <span v-else class="text-sm text-slate-400">No defaults yet · Choose tags to apply automatically</span>
                                    </span>
                                </button>
                                <RouterLink :to="{ name: 'transactions', query: { payer: payer.id } }" :aria-label="`View transactions for ${payer.name}, ${payer.transactions_count ?? 0} total`" class="flex shrink-0 flex-col gap-1 self-start rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:border-indigo-200 hover:text-indigo-700 focus-visible:outline-2 focus-visible:outline-indigo-600 sm:min-w-36 sm:self-auto">
                                    <span><span class="font-bold tabular-nums">{{ (payer.transactions_count ?? 0).toLocaleString() }}</span> {{ payer.transactions_count === 1 ? 'transaction' : 'transactions' }} <span aria-hidden="true" class="ml-1">↗</span></span>
                                    <span class="text-[10px] font-normal text-slate-400">All time · Expenses & savings</span>
                                </RouterLink>
                            </div>
                        </li>
                    </ul>
                    <p v-if="payers.length" class="border-t border-slate-100 bg-slate-50/50 px-5 py-3 text-xs leading-5 text-slate-500">Default tags are added when you select a payer. You can still change them for each transaction.</p>
                </LoadingState>
            </section>
        </main>
        <PayerModal v-if="showingModal" :payer="editingPayer" @close="showingModal = false" @saved="saved" @deleted="deleted" />
    </DashboardLayout>
</template>

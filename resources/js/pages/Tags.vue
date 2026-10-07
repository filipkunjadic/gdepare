<script setup>
import { computed, onMounted, ref } from 'vue';
import DashboardLayout from '../components/DashboardLayout.vue';
import LoadingState from '../components/LoadingState.vue';
import TagModal from '../components/TagModal.vue';
import TagBadge from '../components/TagBadge.vue';
import * as tagsApi from '../api/tags.js';
import { useExpensesStore } from '../stores/expenses.js';
import { useIncomesStore } from '../stores/incomes.js';

const tags = ref([]);
const search = ref('');
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
const visibleTags = computed(() => tags.value.filter(tag => tag.name.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase())));
const inputClass = 'min-w-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-2 focus:outline-indigo-600 disabled:opacity-50';
const buttonClass = 'rounded-lg px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-indigo-600 disabled:opacity-50';

async function refresh() {
    if (saving.value) return;
    loading.value = true;
    loadError.value = '';
    deletingId.value = null;
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
            <div class="mb-6 flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Tags</h1>
                <button type="button" :disabled="loading || saving" :class="buttonClass" class="border border-slate-200 bg-white text-slate-700 hover:bg-slate-100" @click="refresh">Refresh</button>
            </div>
            <button type="button" :disabled="loading || saving" :class="buttonClass" class="mb-5 bg-indigo-600 text-white hover:bg-indigo-700" @click="edit(null)">+ Create tag</button>
            <p v-if="message" role="status" class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ message }}</p>
            <p v-if="actionError" role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ actionError }}</p>
            <label class="mb-5 block max-w-sm">
                <span class="sr-only">Search tags</span>
                <input v-model="search" type="search" placeholder="Search tags…" :disabled="saving" :class="inputClass" class="w-full">
            </label>
            <LoadingState :loading="loading" :error="loadError" @retry="refresh">
                <section aria-label="All tags" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs" :aria-busy="saving">
                    <p class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-medium text-slate-500">{{ visibleTags.length }} {{ visibleTags.length === 1 ? 'tag' : 'tags' }}</p>
                    <p v-if="!visibleTags.length" class="px-5 py-12 text-center text-sm text-slate-500">{{ search.trim() ? 'No tags match your search.' : 'No tags yet. Create your first tag above.' }}</p>
                    <ul v-else class="divide-y divide-slate-100">
                        <li v-for="tag in visibleTags" :key="tag.id">
                            <div class="relative flex items-center justify-between gap-3 px-4 py-3 transition-colors hover:bg-slate-50">
                                <button type="button" :disabled="saving" :aria-label="`Edit ${tag.name}`" class="flex min-w-0 flex-1 items-center gap-2 text-left text-sm font-semibold text-slate-800 after:absolute after:inset-0 after:cursor-pointer focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-inset focus-visible:after:ring-indigo-600 disabled:after:cursor-default" @click="edit(tag)">
                                    <TagBadge :name="tag.name" :icon="tag.icon" :color="tag.color" />
                                </button>
                                <div class="relative z-10 flex shrink-0 gap-1">
                                    <button type="button" :disabled="saving" :aria-label="`Delete ${tag.name}`" :class="buttonClass" class="text-red-600 hover:bg-red-50" @click="deletingId = tag.id; actionError = ''">Delete</button>
                                </div>
                            </div>
                            <div v-if="deletingId === tag.id" class="mx-4 mb-3 rounded-lg border border-red-200 bg-red-50 p-3">
                                <p class="break-words text-sm font-medium text-red-900">Delete “{{ tag.name }}”?</p>
                                <p class="mt-1 text-sm text-red-700">This removes the tag from all transactions. The transactions will be kept.</p>
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

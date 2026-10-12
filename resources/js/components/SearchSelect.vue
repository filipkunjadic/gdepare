<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Array], default: '' },
    options: { type: Array, default: () => [] },
    id: { type: String, required: true },
    label: { type: String, required: true },
    placeholder: { type: String, default: 'Search or add new…' },
    multiple: Boolean,
    required: Boolean,
    autofocus: Boolean,
    loading: Boolean,
    error: { type: String, default: '' },
    maxValues: { type: Number, default: 20 },
});
const emit = defineEmits(['update:modelValue', 'retry']);
const root = ref(null);
const control = ref(null);
const input = ref(null);
const menu = ref(null);
const opened = ref(false);
const search = ref(props.multiple ? '' : props.modelValue);
const filtering = ref(false);
const activeIndex = ref(-1);
const opensAbove = ref(false);
const menuHeight = ref(240);
const announcement = ref('');
const normalize = name => name.trim().toLocaleLowerCase();
const selectedNames = computed(() => props.multiple ? props.modelValue : props.modelValue ? [props.modelValue] : []);
const selectedKeys = computed(() => new Set(selectedNames.value.map(normalize)));
const options = computed(() => {
    const byName = new Map(props.options.map(option => [normalize(option.name), option]));
    for (const name of selectedNames.value) {
        if (!byName.has(normalize(name))) byName.set(normalize(name), { name });
    }
    return [...byName.values()].sort((a, b) => a.name.localeCompare(b.name));
});
const selectedOptions = computed(() => selectedNames.value.map(name => ({
    ...options.value.find(option => normalize(option.name) === normalize(name)), name,
})));
const query = computed(() => props.multiple || filtering.value ? search.value.trim() : '');
const visibleOptions = computed(() => {
    const matches = options.value.filter(option => normalize(option.name).includes(normalize(query.value)));
    if (query.value && !props.loading && !props.error && !options.value.some(option => normalize(option.name) === normalize(query.value))) {
        matches.push({ name: query.value, isNew: true });
    }
    return matches;
});
const atLimit = computed(() => props.multiple && selectedNames.value.length >= props.maxValues);
const activeOptionId = computed(() => opened.value && visibleOptions.value[activeIndex.value] ? `${props.id}-option-${activeIndex.value}` : undefined);

watch(() => props.modelValue, () => {
    if (!props.multiple) search.value = props.modelValue;
    nextTick(positionMenu);
});
watch(visibleOptions, () => {
    activeIndex.value = query.value && visibleOptions.value.length ? 0 : -1;
    nextTick(positionMenu);
});
watch(activeIndex, async () => {
    await nextTick();
    const option = menu.value?.querySelector('[data-active="true"]');
    if (!option) return;
    if (option.offsetTop < menu.value.scrollTop) menu.value.scrollTop = option.offsetTop;
    else if (option.offsetTop + option.offsetHeight > menu.value.scrollTop + menu.value.clientHeight) {
        menu.value.scrollTop = option.offsetTop + option.offsetHeight - menu.value.clientHeight;
    }
});

function positionMenu() {
    if (!opened.value || !control.value) return;
    const bounds = control.value.getBoundingClientRect();
    const dialog = root.value.closest('dialog')?.getBoundingClientRect();
    const viewport = window.visualViewport;
    const top = Math.max(viewport?.offsetTop ?? 0, dialog?.top ?? 0) + 12;
    const bottom = Math.min((viewport?.offsetTop ?? 0) + (viewport?.height ?? window.innerHeight), dialog?.bottom ?? window.innerHeight) - 12;
    const below = bottom - bounds.bottom - 6;
    const above = bounds.top - top - 6;
    opensAbove.value = below < 200 && above > below;
    menuHeight.value = Math.max(48, Math.min(240, opensAbove.value ? above : below));
}

function openMenu() {
    if (input.value?.matches(':disabled')) return;
    opened.value = true;
    nextTick(positionMenu);
}

function closeMenu() {
    opened.value = false;
    filtering.value = false;
    activeIndex.value = -1;
    search.value = props.multiple ? '' : props.modelValue;
}

function focusInput() {
    input.value?.focus();
    openMenu();
}

function toggleMenu() {
    if (opened.value) closeMenu();
    else focusInput();
}

function updateSearch(event) {
    search.value = event.target.value;
    filtering.value = true;
    openMenu();
}

function isUnavailable(option) {
    return atLimit.value && !selectedKeys.value.has(normalize(option.name));
}

function choose(option) {
    if (!option || isUnavailable(option) || input.value?.matches(':disabled')) return;
    if (props.multiple) {
        const selected = selectedKeys.value.has(normalize(option.name));
        emit('update:modelValue', selected
            ? selectedNames.value.filter(name => normalize(name) !== normalize(option.name))
            : [...selectedNames.value, option.name]);
        announcement.value = `${option.name} ${selected ? 'removed' : 'added'}.`;
        search.value = '';
        focusInput();
    } else {
        emit('update:modelValue', option.name);
        input.value?.focus();
        closeMenu();
        search.value = option.name;
    }
}

function remove(name) {
    emit('update:modelValue', props.multiple ? selectedNames.value.filter(selected => selected !== name) : '');
    announcement.value = `${name} removed.`;
    search.value = '';
    focusInput();
}

function handleKeydown(event) {
    if (event.isComposing) return;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        openMenu();
        const count = visibleOptions.value.length;
        if (!count) return;
        activeIndex.value = activeIndex.value < 0
            ? (event.key === 'ArrowDown' ? 0 : count - 1)
            : (activeIndex.value + (event.key === 'ArrowDown' ? 1 : -1) + count) % count;
    } else if (event.key === 'Enter' && opened.value) {
        event.preventDefault();
        const option = visibleOptions.value[activeIndex.value];
        if (option) choose(option);
        else closeMenu();
    } else if (event.key === 'Backspace' && props.multiple && !search.value && selectedNames.value.length) {
        event.preventDefault();
        remove(selectedNames.value.at(-1));
    }
}

function escape(event) {
    if (!opened.value || event.isComposing) return;
    event.preventDefault();
    event.stopPropagation();
    closeMenu();
}

function outsidePointer(event) {
    if (!root.value?.contains(event.target)) closeMenu();
}

async function focusOut() {
    await nextTick();
    if (!root.value?.contains(document.activeElement)) closeMenu();
}

let resizeObserver;
onMounted(() => {
    document.addEventListener('pointerdown', outsidePointer);
    document.addEventListener('scroll', positionMenu, true);
    window.addEventListener('resize', positionMenu);
    window.visualViewport?.addEventListener('resize', positionMenu);
    resizeObserver = new ResizeObserver(positionMenu);
    resizeObserver.observe(control.value);
});
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', outsidePointer);
    document.removeEventListener('scroll', positionMenu, true);
    window.removeEventListener('resize', positionMenu);
    window.visualViewport?.removeEventListener('resize', positionMenu);
    resizeObserver?.disconnect();
});
</script>

<template>
    <div ref="root" class="flex min-w-0 flex-col gap-2" @focusout="focusOut" @keydown.esc="escape">
        <label :for="id" class="block text-sm font-medium text-slate-700">{{ label }} <span v-if="!required" class="font-normal text-slate-500">(optional)</span></label>
        <div ref="control" class="relative">
            <div class="flex min-h-12 items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 focus-within:border-indigo-600 focus-within:outline-2 focus-within:outline-indigo-600" @click.self="focusInput">
                <slot v-if="!multiple && !filtering && selectedOptions.length" name="prefix" :option="selectedOptions[0]" />
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5" @click.self="focusInput">
                    <template v-if="multiple">
                        <span v-for="option in selectedOptions" :key="option.name" class="inline-flex max-w-full items-center gap-1.5 rounded-md bg-slate-100 py-1 pr-1 pl-2 text-sm text-slate-700">
                            <slot name="token" :option="option"><span :title="option.name" class="min-w-0 truncate">{{ option.name }}</span></slot>
                            <button type="button" :aria-label="`Remove ${option.name}`" class="flex size-5 shrink-0 items-center justify-center rounded text-slate-500 hover:bg-slate-200 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-indigo-600" @click="remove(option.name)">
                                <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" class="size-3.5"><path d="m4 4 8 8M12 4l-8 8" /></svg>
                            </button>
                        </span>
                    </template>
                    <input :id="id" ref="input" :value="search" :required="required" :autofocus="autofocus" :placeholder="multiple && selectedNames.length ? 'Add tags…' : placeholder" maxlength="255" autocomplete="off" role="combobox" aria-autocomplete="list" aria-haspopup="listbox" :aria-expanded="opened" :aria-busy="loading" :aria-controls="`${id}-options`" :aria-activedescendant="activeOptionId" :aria-describedby="`${id}-help`" :class="multiple ? 'min-w-28' : 'min-w-0'" class="h-7 w-0 flex-1 border-0 bg-transparent p-0 text-base text-slate-900 outline-none placeholder:text-slate-400" @focus="openMenu" @click="openMenu" @input="updateSearch" @keydown="handleKeydown">
                </div>
                <button v-if="!multiple && modelValue" type="button" :aria-label="`Clear ${label.toLocaleLowerCase()}`" class="flex size-6 shrink-0 items-center justify-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-indigo-600" @mousedown.prevent @click="remove(modelValue)">
                    <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" class="size-4"><path d="m4 4 8 8M12 4l-8 8" /></svg>
                </button>
                <button type="button" tabindex="-1" :aria-label="`${opened ? 'Close' : 'Open'} ${label.toLocaleLowerCase()} options`" :aria-expanded="opened" :aria-controls="`${id}-options`" class="flex size-6 shrink-0 items-center justify-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700" @mousedown.prevent @click="toggleMenu">
                    <svg v-if="loading" aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="size-4 animate-spin motion-reduce:animate-none"><path d="M17 10a7 7 0 1 1-7-7" /></svg>
                    <svg v-else aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" :class="opened ? 'rotate-180' : ''" class="size-4"><path d="m5 7.5 5 5 5-5" /></svg>
                </button>
            </div>
            <div v-if="opened" :class="opensAbove ? 'bottom-full mb-1.5' : 'top-full mt-1.5'" class="absolute inset-x-0 z-30 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                <div :id="`${id}-options`" ref="menu" role="listbox" :aria-label="label" :aria-multiselectable="multiple || undefined" :aria-busy="loading" :style="{ maxHeight: `${menuHeight}px` }" class="relative overflow-y-auto overscroll-contain p-1">
                    <button v-for="(option, index) in visibleOptions" :id="`${id}-option-${index}`" :key="option.name" type="button" role="option" tabindex="-1" :aria-selected="selectedKeys.has(normalize(option.name))" :disabled="isUnavailable(option)" :data-active="activeIndex === index" :class="activeIndex === index ? 'bg-indigo-50 text-indigo-900' : 'text-slate-700 hover:bg-slate-50'" class="flex min-h-10 w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm disabled:cursor-not-allowed disabled:opacity-40" @mousedown.prevent @click="choose(option)">
                        <template v-if="option.isNew">
                            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="size-5 shrink-0 text-indigo-600"><path d="M10 4v12M4 10h12" /></svg>
                            <span class="min-w-0 flex-1 break-words text-indigo-700">Add “{{ option.name }}”</span>
                        </template>
                        <template v-else>
                            <slot name="option" :option="option"><span class="min-w-0 flex-1 break-words">{{ option.name }}</span></slot>
                            <svg v-if="selectedKeys.has(normalize(option.name))" aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="ml-auto size-4 shrink-0 text-indigo-600"><path d="m4 10 4 4 8-8" /></svg>
                        </template>
                    </button>
                    <p v-if="loading" role="status" class="px-3 py-2.5 text-sm text-slate-500">Loading…</p>
                    <p v-else-if="!visibleOptions.length" role="status" class="px-3 py-2.5 text-sm text-slate-500">{{ error ? 'Options could not be loaded.' : 'No options yet. Type a name to add one.' }}</p>
                </div>
            </div>
        </div>
        <p :id="`${id}-help`" :class="atLimit ? 'text-xs text-slate-500' : 'sr-only'">{{ atLimit ? `You can select up to ${maxValues} tags.` : 'Type to search. Use arrow keys to browse, Enter to select, and Escape to close. To add a new name, choose Add.' }}</p>
        <p v-if="error" role="alert" class="text-sm text-red-700">{{ error }} <button type="button" class="rounded font-medium underline focus-visible:outline-2 focus-visible:outline-indigo-600" @click="emit('retry')">Retry</button></p>
        <span role="status" class="sr-only">{{ announcement }}</span>
    </div>
</template>

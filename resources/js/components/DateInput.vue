<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { session } from '../session.js';
import { formatDate, parseDate, todayDate } from '../utils/formatDate.js';
import { calendarDateForKey, calendarWeeks, shiftCalendarDate } from '../utils/calendar.js';

const props = defineProps({
    modelValue: { type: String, default: '' },
    id: String,
    name: String,
    required: Boolean,
    disabled: Boolean,
    accent: { type: String, default: 'indigo' },
});
const emit = defineEmits(['update:modelValue']);
const auth = useAuthStore();
const format = computed(() => auth.user?.date_format ?? 'd.m.Y');
const draft = ref('');
const root = ref(null);
const control = ref(null);
const input = ref(null);
const trigger = ref(null);
const calendar = ref(null);
const opened = ref(false);
const today = ref(todayDate());
const focusedDate = ref(today.value);
const visibleMonth = ref(today.value.slice(0, 7));
const pickerId = `date-picker-${useId()}`;
const weeks = computed(() => calendarWeeks(visibleMonth.value));
const monthLabel = computed(() => new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric', timeZone: 'UTC' })
    .format(new Date(`${visibleMonth.value}-01T12:00:00Z`)));
const fullDate = new Intl.DateTimeFormat('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
const weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const accentClasses = computed(() => props.accent === 'emerald' ? {
    control: 'focus-within:border-emerald-600 focus-within:outline-emerald-600',
    focus: 'focus-visible:outline-emerald-600',
    selected: 'bg-emerald-600 text-white hover:bg-emerald-700',
    today: 'ring-1 ring-inset ring-emerald-300',
    action: 'text-emerald-700 hover:bg-emerald-50',
} : {
    control: 'focus-within:border-indigo-600 focus-within:outline-indigo-600',
    focus: 'focus-visible:outline-indigo-600',
    selected: 'bg-indigo-600 text-white hover:bg-indigo-700',
    today: 'ring-1 ring-inset ring-indigo-300',
    action: 'text-indigo-700 hover:bg-indigo-50',
});

watch([() => props.modelValue, format], () => {
    draft.value = formatDate(props.modelValue, format.value);
    input.value?.setCustomValidity('');
    if (opened.value && parseDate(props.modelValue, 'Y-m-d') && shiftCalendarDate(props.modelValue)) showDate(props.modelValue);
}, { immediate: true });
watch(() => props.disabled, disabled => {
    if (disabled) closeCalendar();
});

function update(event) {
    draft.value = event.target.value;
    const value = parseDate(draft.value, format.value);
    event.target.setCustomValidity(draft.value && !value ? `Enter a valid date (${session.dateFormats[format.value]}).` : '');
    if (value || !draft.value) emit('update:modelValue', value ?? '');
}

function isDisabled() {
    return props.disabled || input.value?.matches(':disabled');
}

function showDate(value) {
    focusedDate.value = value;
    visibleMonth.value = value.slice(0, 7);
}

async function openCalendar(focusDay = false) {
    if (isDisabled()) return;
    if (!opened.value) {
        today.value = todayDate();
        const selected = parseDate(draft.value, format.value);
        showDate(selected && shiftCalendarDate(selected) ? selected : today.value);
        opened.value = true;
        await nextTick();
        if (!opened.value || isDisabled()) return;
        calendar.value.showPopover?.({ source: trigger.value });
        positionCalendar();
    }
    if (focusDay) await focusSelectedDay();
}

function closeCalendar(restoreFocus = false) {
    if (calendar.value?.hidePopover && calendar.value.matches(':popover-open')) calendar.value.hidePopover();
    opened.value = false;
    if (restoreFocus) input.value?.focus({ preventScroll: true });
}

function toggleCalendar() {
    if (opened.value) closeCalendar(true);
    else openCalendar(true);
}

function pick(value) {
    if (isDisabled()) return;
    draft.value = formatDate(value, format.value);
    input.value?.setCustomValidity('');
    emit('update:modelValue', value);
    closeCalendar(true);
}

function moveMonth(offset) {
    const value = shiftCalendarDate(focusedDate.value, { months: offset });
    if (value) showDate(value);
}

async function focusSelectedDay() {
    await nextTick();
    if (opened.value) calendar.value?.querySelector(`[data-date="${focusedDate.value}"]`)?.focus({ preventScroll: true });
}

function inputKeydown(event) {
    if (event.isComposing) return;
    if (event.key === 'ArrowDown' || (event.key === 'Enter' && opened.value)) {
        event.preventDefault();
        openCalendar(true);
    }
}

function dayKeydown(event, value) {
    if (event.isComposing) return;
    if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End', 'PageUp', 'PageDown'].includes(event.key)) return;
    event.preventDefault();
    const next = calendarDateForKey(value, event.key, event.shiftKey);
    if (!next) return;
    showDate(next);
    focusSelectedDay();
}

function escape(event) {
    if (!opened.value || event.isComposing) return;
    event.preventDefault();
    event.stopPropagation();
    closeCalendar(true);
}

function outsidePointer(event) {
    if (!root.value?.contains(event.target)) closeCalendar();
}

async function focusOut() {
    await nextTick();
    if (!root.value?.contains(document.activeElement)) closeCalendar();
}

function positionCalendar() {
    if (!opened.value || !control.value || !calendar.value) return;
    const field = control.value.getBoundingClientRect();
    const dialog = root.value.closest('dialog')?.getBoundingClientRect();
    const viewport = window.visualViewport;
    const viewportLeft = viewport?.offsetLeft ?? 0;
    const viewportTop = viewport?.offsetTop ?? 0;
    const left = Math.max(viewportLeft, dialog?.left ?? viewportLeft) + 12;
    const right = Math.min(viewportLeft + (viewport?.width ?? window.innerWidth), dialog?.right ?? window.innerWidth) - 12;
    const top = Math.max(viewportTop, dialog?.top ?? viewportTop) + 12;
    const bottom = Math.min(viewportTop + (viewport?.height ?? window.innerHeight), dialog?.bottom ?? window.innerHeight) - 12;
    if (field.bottom < top || field.top > bottom) return closeCalendar();
    const popup = calendar.value;
    popup.style.maxWidth = `${Math.max(0, right - left)}px`;
    popup.style.maxHeight = `${Math.max(0, bottom - top)}px`;
    const bounds = popup.getBoundingClientRect();
    popup.style.left = `${Math.max(left, Math.min(field.left, right - bounds.width))}px`;
    popup.style.top = `${field.bottom + 8 + bounds.height <= bottom ? field.bottom + 8
        : field.top - 8 - bounds.height >= top ? field.top - 8 - bounds.height : top}px`;
}

let resizeObserver;
onMounted(() => {
    document.addEventListener('pointerdown', outsidePointer);
    document.addEventListener('scroll', positionCalendar, true);
    window.addEventListener('resize', positionCalendar);
    window.visualViewport?.addEventListener('resize', positionCalendar);
    window.visualViewport?.addEventListener('scroll', positionCalendar);
    resizeObserver = new ResizeObserver(positionCalendar);
    resizeObserver.observe(control.value);
});
onBeforeUnmount(() => {
    closeCalendar();
    document.removeEventListener('pointerdown', outsidePointer);
    document.removeEventListener('scroll', positionCalendar, true);
    window.removeEventListener('resize', positionCalendar);
    window.visualViewport?.removeEventListener('resize', positionCalendar);
    window.visualViewport?.removeEventListener('scroll', positionCalendar);
    resizeObserver?.disconnect();
});
</script>

<template>
    <div ref="root" class="relative min-w-0" @focusout="focusOut" @keydown.esc="escape">
        <div ref="control" :class="accentClasses.control" class="flex h-[50px] w-full items-center gap-2 rounded-xl border border-slate-300 bg-white pr-2 pl-3.5 transition-colors focus-within:outline-2">
            <input :id="id" ref="input" :name="name" :value="draft" :required="required" :disabled="disabled" :placeholder="session.dateFormats[format]" :aria-describedby="`${pickerId}-help`" role="combobox" aria-autocomplete="none" aria-haspopup="dialog" :aria-expanded="opened" :aria-controls="pickerId" type="text" autocomplete="off" class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-base leading-6 text-slate-900 outline-none placeholder:text-slate-400 disabled:opacity-50" @click="openCalendar()" @input="update" @keydown="inputKeydown">
            <button ref="trigger" type="button" :disabled="disabled" :popovertarget="pickerId" :aria-expanded="opened" :aria-controls="pickerId" aria-haspopup="dialog" :aria-label="opened ? 'Close calendar' : 'Choose date from calendar'" :class="accentClasses.focus" class="flex size-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 disabled:opacity-50" @click.prevent="toggleCalendar">
                <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 5h16v16H4V5Zm0 5h16M8 2v6m8-6v6" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>
        </div>
        <p :id="`${pickerId}-help`" class="sr-only">Enter a date as {{ session.dateFormats[format] }}, or press Down Arrow to choose from the calendar.</p>
        <div :id="pickerId" ref="calendar" v-show="opened" popover="auto" role="dialog" aria-label="Choose date" :aria-describedby="`${pickerId}-keyboard-help`" class="fixed inset-auto m-0 w-80 max-w-[calc(100vw-2rem)] overflow-y-auto overscroll-contain rounded-2xl border border-slate-200 bg-white p-3 text-slate-900 shadow-xl" @beforetoggle="opened = $event.newState === 'open'">
            <div class="mb-2 flex items-center justify-between gap-2">
                <button type="button" aria-label="Previous month" :disabled="disabled || visibleMonth === '0001-01'" :class="accentClasses.focus" class="flex size-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 focus-visible:outline-2 disabled:opacity-30" @click="moveMonth(-1)"><svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m14 6-6 6 6 6" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
                <h3 :id="`${pickerId}-month`" aria-live="polite" class="text-sm font-semibold">{{ monthLabel }}</h3>
                <button type="button" aria-label="Next month" :disabled="disabled || visibleMonth === '9999-12'" :class="accentClasses.focus" class="flex size-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 focus-visible:outline-2 disabled:opacity-30" @click="moveMonth(1)"><svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m10 6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
            </div>
            <table role="grid" :aria-labelledby="`${pickerId}-month`" class="w-full table-fixed border-separate border-spacing-0.5 text-center text-sm">
                <thead><tr><th v-for="day in weekdays" :key="day" scope="col" :abbr="day" class="pb-2 text-[11px] font-medium text-slate-400">{{ day.slice(0, 2) }}</th></tr></thead>
                <tbody>
                    <tr v-for="(week, index) in weeks" :key="index">
                        <td v-for="(day, dayIndex) in week" :key="dayIndex" :aria-selected="day ? day === modelValue : undefined" class="p-0">
                            <button v-if="day" type="button" :data-date="day" :disabled="disabled" :tabindex="day === focusedDate ? 0 : -1" :aria-label="fullDate.format(new Date(`${day}T12:00:00Z`))" :aria-current="day === today ? 'date' : undefined" :class="[accentClasses.focus, day === modelValue ? accentClasses.selected : day.slice(0, 7) === visibleMonth ? 'text-slate-700 hover:bg-slate-100' : 'text-slate-400 hover:bg-slate-50', day === today && day !== modelValue ? accentClasses.today : '']" class="h-9 w-full rounded-lg text-sm font-medium tabular-nums focus-visible:relative focus-visible:outline-2 focus-visible:outline-offset-1 disabled:opacity-40" @focus="focusedDate = day" @click="pick(day)" @keydown="dayKeydown($event, day)">{{ Number(day.slice(8)) }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-3">
                <span class="text-xs text-slate-500">{{ formatDate(modelValue, format) || 'Choose a date' }}</span>
                <button type="button" :disabled="disabled" :class="[accentClasses.action, accentClasses.focus]" class="rounded-lg px-3 py-1.5 text-xs font-semibold focus-visible:outline-2 disabled:opacity-40" @click="pick(today)">Today</button>
            </div>
            <p :id="`${pickerId}-keyboard-help`" class="sr-only">Use arrow keys to move between dates, Page Up or Page Down to change month, and Enter to choose. Escape closes the calendar.</p>
        </div>
    </div>
</template>

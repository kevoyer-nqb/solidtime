<script setup lang="ts">
import { ref, computed, nextTick, inject, type ComputedRef } from 'vue';
import type { TimesheetGridCell } from '@/utils/useTimesheetGrid';
import { useTimesheetGridStore } from '@/utils/useTimesheetGrid';
import {
    formatHumanReadableDuration,
    parseTimeInput,
} from '@/packages/ui/src/utils/time';
import type { Organization } from '@/packages/api/src';

const props = defineProps<{
    cell: TimesheetGridCell;
    projectId: string | null;
    taskId: string | null;
    date: string;
}>();

const store = useTimesheetGridStore();
const organization = inject<ComputedRef<Organization>>('organization');

const intervalFormat = computed(
    () => organization?.value?.interval_format ?? 'hours-minutes'
);
const numberFormat = computed(
    () => organization?.value?.number_format ?? 'point'
);
const defaultUnit = computed(() =>
    intervalFormat.value === 'decimal' ? 'hours' as const : 'minutes' as const
);

const isEditing = ref(false);
const editValue = ref('');
const isSaving = ref(false);
const hasError = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);

const hasEntries = computed(() => props.cell.time_entry_ids.length > 0);
const hasMultipleEntries = computed(() => props.cell.time_entry_ids.length > 1);
const hasDuration = computed(() => props.cell.total_seconds > 0);

const formattedDuration = computed(() => {
    if (!hasDuration.value) return '';
    return formatHumanReadableDuration(
        props.cell.total_seconds,
        intervalFormat.value,
        numberFormat.value
    );
});

function enterEditMode() {
    if (isSaving.value) return;
    isEditing.value = true;
    editValue.value = hasDuration.value ? formattedDuration.value : '';
    hasError.value = false;
    nextTick(() => {
        inputRef.value?.focus();
        inputRef.value?.select();
    });
}

function cancelEdit() {
    isEditing.value = false;
    editValue.value = '';
    hasError.value = false;
}

async function saveEdit() {
    if (isSaving.value) return;

    const trimmedValue = editValue.value.trim();

    // Empty input: cancel
    if (!trimmedValue) {
        cancelEdit();
        return;
    }

    const parsedSeconds = parseTimeInput(trimmedValue, defaultUnit.value);

    // Invalid input: show error and revert
    if (parsedSeconds === null || isNaN(parsedSeconds)) {
        hasError.value = true;
        setTimeout(() => {
            cancelEdit();
        }, 600);
        return;
    }

    // Zero duration: cancel (do not create zero-duration entries)
    if (parsedSeconds <= 0) {
        cancelEdit();
        return;
    }

    // No change from current value: cancel
    if (hasEntries.value && parsedSeconds === props.cell.total_seconds) {
        cancelEdit();
        return;
    }

    isSaving.value = true;

    try {
        if (hasEntries.value) {
            // Update existing entry/entries
            await store.updateCellDuration(
                props.cell.time_entry_ids,
                parsedSeconds,
                props.cell.total_seconds
            );
        } else {
            // Create new entry
            await store.createCellEntry(
                props.projectId,
                props.taskId,
                props.date,
                parsedSeconds
            );
        }
    } finally {
        isSaving.value = false;
        isEditing.value = false;
        editValue.value = '';
    }
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        cancelEdit();
    } else if (event.key === 'Enter') {
        saveEdit();
    }
}
</script>

<template>
    <div
        class="min-h-[32px] flex items-center justify-center relative rounded transition-colors"
        :class="{
            'cursor-pointer hover:bg-card-background': !isEditing && hasDuration,
            'cursor-cell hover:bg-card-background/50': !isEditing && !hasDuration,
            'opacity-50': isSaving,
        }"
        @click="!isEditing ? enterEditMode() : undefined">
        <!-- Display mode -->
        <template v-if="!isEditing">
            <span
                v-if="hasDuration"
                class="text-sm text-text-primary">
                {{ formattedDuration }}
                <span
                    v-if="hasMultipleEntries"
                    class="text-[10px] text-text-tertiary align-super ml-0.5">
                    {{ cell.time_entry_ids.length }}
                </span>
            </span>
            <span
                v-else
                class="text-text-tertiary opacity-0 hover:opacity-100 transition-opacity text-xs">
                +
            </span>
        </template>

        <!-- Edit mode -->
        <input
            v-if="isEditing"
            ref="inputRef"
            v-model="editValue"
            type="text"
            class="w-full text-center bg-transparent border-0 border-b-2 focus:ring-0 text-sm p-1 text-text-primary"
            :class="hasError ? 'border-red-500' : 'border-accent-300'"
            @keydown="onKeydown"
            @blur="saveEdit" />
    </div>
</template>

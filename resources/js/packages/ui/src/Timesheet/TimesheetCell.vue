<script setup lang="ts">
import { ref, computed, nextTick } from 'vue';
import type { TimesheetCell } from '@/types/timesheet';
import LoadingSpinner from '@/packages/ui/src/LoadingSpinner.vue';

const props = defineProps<{
    cell: TimesheetCell;
    isLoading: boolean;
}>();

const emit = defineEmits<{
    update: [hours: number];
    navigate: [direction: 'up' | 'down' | 'left' | 'right'];
}>();

const isEditing = ref(false);
const inputValue = ref('');
const inputRef = ref<HTMLInputElement | null>(null);
const hasError = ref(false);
const errorMessage = ref('');

const displayValue = computed(() => {
    if (props.cell.hours === 0) return '';
    const h = Math.floor(props.cell.hours);
    const m = Math.round((props.cell.hours - h) * 60);
    if (m === 0) return `${h}`;
    return props.cell.hours.toFixed(2).replace(/\.?0+$/, '');
});

function startEditing() {
    if (props.cell.isLoading) return;
    isEditing.value = true;
    inputValue.value = displayValue.value;
    hasError.value = false;
    nextTick(() => {
        inputRef.value?.focus();
        inputRef.value?.select();
    });
}

function validateInput(value: string): { valid: boolean; hours: number; error: string } {
    if (value === '' || value === '0') {
        return { valid: true, hours: 0, error: '' };
    }

    // Support "1:30" format (h:mm)
    const colonMatch = value.match(/^(\d+):(\d{1,2})$/);
    if (colonMatch) {
        const h = parseInt(colonMatch[1], 10);
        const m = parseInt(colonMatch[2], 10);
        if (m > 59) {
            return { valid: false, hours: 0, error: 'Minutes must be 0-59' };
        }
        const totalHours = h + m / 60;
        if (totalHours > 24) {
            return { valid: false, hours: 0, error: 'Maximum 24 hours per day' };
        }
        return { valid: true, hours: Math.round(totalHours * 100) / 100, error: '' };
    }

    const hours = parseFloat(value);

    if (isNaN(hours)) {
        return { valid: false, hours: 0, error: 'Enter a valid number' };
    }

    if (hours < 0) {
        return { valid: false, hours: 0, error: 'Hours cannot be negative' };
    }

    if (hours > 24) {
        return { valid: false, hours: 0, error: 'Maximum 24 hours per day' };
    }

    return { valid: true, hours: Math.round(hours * 100) / 100, error: '' };
}

function handleSave() {
    const { valid, hours, error } = validateInput(inputValue.value);

    if (!valid) {
        hasError.value = true;
        errorMessage.value = error;
        return;
    }

    isEditing.value = false;

    if (hours !== props.cell.hours) {
        emit('update', hours);
    }
}

function handleCancel() {
    isEditing.value = false;
    hasError.value = false;
    inputValue.value = displayValue.value;
}

function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter') {
        handleSave();
        emit('navigate', 'down');
    } else if (event.key === 'Escape') {
        handleCancel();
    } else if (event.key === 'Tab') {
        event.preventDefault();
        handleSave();
        emit('navigate', event.shiftKey ? 'left' : 'right');
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        handleSave();
        emit('navigate', 'up');
    } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        handleSave();
        emit('navigate', 'down');
    } else if (event.key === 'ArrowLeft' && inputRef.value?.selectionStart === 0) {
        event.preventDefault();
        handleSave();
        emit('navigate', 'left');
    } else if (
        event.key === 'ArrowRight' &&
        inputRef.value?.selectionStart === inputValue.value.length
    ) {
        event.preventDefault();
        handleSave();
        emit('navigate', 'right');
    }
}

function handleDisplayKeydown(event: KeyboardEvent) {
    if (event.key === 'ArrowUp') {
        event.preventDefault();
        emit('navigate', 'up');
    } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        emit('navigate', 'down');
    } else if (event.key === 'ArrowLeft' || (event.key === 'Tab' && event.shiftKey)) {
        event.preventDefault();
        emit('navigate', 'left');
    } else if (event.key === 'ArrowRight' || (event.key === 'Tab' && !event.shiftKey)) {
        event.preventDefault();
        emit('navigate', 'right');
    } else if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        startEditing();
    }
}
</script>

<template>
    <div
        class="relative h-9 w-full rounded"
        :class="{
            'cursor-pointer hover:bg-primary-100/50': !isEditing && !cell.isLoading,
            'ring-2 ring-red-500': hasError || cell.hasError,
        }">
        <!-- Loading State -->
        <div
            v-if="cell.isLoading"
            class="absolute inset-0 flex items-center justify-center rounded bg-tertiary/30">
            <LoadingSpinner class="h-4 w-4"></LoadingSpinner>
        </div>

        <!-- Display Mode -->
        <div
            v-if="!isEditing"
            class="h-full w-full flex items-center justify-center text-sm rounded"
            :class="{
                'text-text-tertiary': !displayValue,
                'text-text-primary font-medium': !!displayValue,
            }"
            role="button"
            :tabindex="0"
            :aria-label="`${cell.hours} hours on ${cell.date}`"
            @click="startEditing"
            @keydown="handleDisplayKeydown">
            {{ displayValue || '-' }}
        </div>

        <!-- Edit Mode -->
        <input
            v-else
            ref="inputRef"
            v-model="inputValue"
            type="text"
            inputmode="decimal"
            class="h-full w-full text-center text-sm border-2 border-primary-500 rounded focus:outline-none bg-card-background"
            :class="{ 'border-red-500': hasError }"
            :aria-label="`Edit hours for ${cell.date}`"
            @blur="handleSave"
            @keydown="handleKeydown" />

        <!-- Error Tooltip -->
        <div
            v-if="hasError && errorMessage"
            class="absolute top-full left-1/2 transform -translate-x-1/2 mt-1 px-2 py-1 bg-red-600 text-white text-xs rounded whitespace-nowrap z-10"
            role="alert">
            {{ errorMessage }}
        </div>
    </div>
</template>

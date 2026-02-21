<script setup lang="ts">
import { computed } from 'vue';
import type { WeekSummary } from '@/types/timesheet';
import { ChevronDownIcon, ChevronRightIcon } from '@heroicons/vue/20/solid';
import { useTimesheetStore } from '@/utils/useTimesheet';
import { storeToRefs } from 'pinia';
import TimesheetGrid from './TimesheetGrid.vue';
import TimesheetAddTask from './TimesheetAddTask.vue';
import LoadingSpinner from '@/packages/ui/src/LoadingSpinner.vue';

const props = defineProps<{
    week: WeekSummary;
    isExpanded: boolean;
}>();

const emit = defineEmits<{
    toggle: [];
}>();

const timesheetStore = useTimesheetStore();
const { weekDataMap, loadingWeeks, recentTasks } = storeToRefs(timesheetStore);

const weekData = computed(() => weekDataMap.value.get(props.week.week_start));
const isLoadingGrid = computed(() => loadingWeeks.value.has(props.week.week_start));

function formatTotalHours(seconds: number): string {
    if (seconds === 0) return '0h';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.round((seconds % 3600) / 60);
    if (minutes === 0) return `${hours}h`;
    if (hours === 0) return `${minutes}m`;
    return `${hours}h ${minutes}m`;
}

function handleCellUpdate(rowIndex: number, dayIndex: number, hours: number) {
    timesheetStore.updateCell(props.week.week_start, rowIndex, dayIndex, hours);
}

function handleRemoveRow(rowIndex: number) {
    const data = weekDataMap.value.get(props.week.week_start);
    if (data) {
        data.rows.splice(rowIndex, 1);
    }
}

function handleAddTask(projectId: string | null, taskId: string | null) {
    timesheetStore.addTaskRow(props.week.week_start, projectId, taskId);
}
</script>

<template>
    <div class="border-b border-default-background-separator">
        <!-- Accordion Header -->
        <button
            class="w-full flex items-center justify-between py-3 px-4 lg:px-6 hover:bg-tertiary/20 transition-colors"
            :aria-expanded="isExpanded"
            :aria-label="`${week.label} - ${formatTotalHours(week.total_seconds)}`"
            @click="emit('toggle')">
            <div class="flex items-center gap-3">
                <component
                    :is="isExpanded ? ChevronDownIcon : ChevronRightIcon"
                    class="w-5 h-5 text-icon-default transition-transform"></component>
                <span class="text-sm font-medium text-text-primary">
                    {{ week.label }}
                </span>
                <span class="text-xs text-text-tertiary">
                    {{ week.week_start }} - {{ week.week_end }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="text-sm font-semibold"
                    :class="{
                        'text-text-tertiary': week.total_seconds === 0,
                        'text-text-primary': week.total_seconds > 0,
                    }">
                    {{ formatTotalHours(week.total_seconds) }}
                </span>
            </div>
        </button>

        <!-- Accordion Content -->
        <div v-if="isExpanded" class="pb-4">
            <div v-if="isLoadingGrid" class="flex justify-center py-8">
                <LoadingSpinner></LoadingSpinner>
                <span class="text-sm text-text-secondary">Loading week data...</span>
            </div>

            <template v-else-if="weekData">
                <TimesheetGrid
                    :week-data="weekData"
                    :is-loading="false"
                    @update-cell="handleCellUpdate"
                    @remove-row="handleRemoveRow">
                </TimesheetGrid>

                <div class="px-4 lg:px-6 pt-3">
                    <TimesheetAddTask
                        :recent-tasks="recentTasks"
                        @add-task="handleAddTask">
                    </TimesheetAddTask>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, inject, type ComputedRef } from 'vue';
import type { TimesheetGridRow, TimesheetGridCell } from '@/utils/useTimesheetGrid';
import { formatHumanReadableDuration } from '@/packages/ui/src/utils/time';
import type { Organization } from '@/packages/api/src';

const props = defineProps<{
    row: TimesheetGridRow;
    days: string[];
}>();

const organization = inject<ComputedRef<Organization>>('organization');

const intervalFormat = computed(
    () => organization?.value?.interval_format ?? 'hours-minutes'
);
const numberFormat = computed(
    () => organization?.value?.number_format ?? 'point'
);

function getCellForDay(day: string): TimesheetGridCell {
    const cell = props.row.cells.find((c) => c.date === day);
    if (cell) return cell;
    return { date: day, total_seconds: 0, time_entry_ids: [] };
}

const formattedRowTotal = computed(() => {
    return formatHumanReadableDuration(
        props.row.row_total,
        intervalFormat.value,
        numberFormat.value
    );
});
</script>

<template>
    <tr class="hover:bg-card-background/50 transition-colors">
        <td class="py-2.5 px-3">
            <div class="flex items-center gap-2">
                <span
                    v-if="row.project_color"
                    class="inline-block w-2 h-2 rounded-full flex-shrink-0"
                    :style="{ backgroundColor: row.project_color }"></span>
                <div class="min-w-0">
                    <div
                        class="text-sm font-medium truncate"
                        :class="
                            row.project_name
                                ? 'text-text-primary'
                                : 'text-text-tertiary italic'
                        ">
                        {{ row.project_name || 'No Project' }}
                    </div>
                    <div
                        v-if="row.task_name"
                        class="text-xs text-text-tertiary truncate">
                        {{ row.task_name }}
                    </div>
                </div>
            </div>
        </td>
        <td
            v-for="day in days"
            :key="day"
            class="text-center py-2.5 px-1">
            <!-- Cell placeholder: will be replaced with TimesheetGridCell in Task 2 -->
            <div class="text-sm text-text-secondary min-h-[28px] flex items-center justify-center">
                <span v-if="getCellForDay(day).total_seconds > 0">
                    {{ formatHumanReadableDuration(
                        getCellForDay(day).total_seconds,
                        intervalFormat,
                        numberFormat
                    ) }}
                </span>
            </div>
        </td>
        <td class="text-center py-2.5 px-3">
            <span class="text-sm font-semibold text-text-primary">
                {{ formattedRowTotal }}
            </span>
        </td>
    </tr>
</template>

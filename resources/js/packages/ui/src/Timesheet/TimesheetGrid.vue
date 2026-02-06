<script setup lang="ts">
import { computed } from 'vue';
import type { TimesheetWeekData } from '@/types/timesheet';
import TimesheetCell from './TimesheetCell.vue';
import TimesheetRowHeader from './TimesheetRowHeader.vue';
import dayjs from 'dayjs';

const props = defineProps<{
    weekData: TimesheetWeekData;
    isLoading: boolean;
}>();

const emit = defineEmits<{
    updateCell: [rowIndex: number, dayIndex: number, hours: number];
    removeRow: [rowIndex: number];
}>();

const weekDays = computed(() => {
    const start = dayjs(props.weekData.week_start);
    const today = dayjs().format('YYYY-MM-DD');
    return Array.from({ length: 7 }, (_, i) => {
        const day = start.add(i, 'day');
        return {
            date: day.format('YYYY-MM-DD'),
            dayName: day.format('ddd'),
            dayNumber: day.format('D'),
            isToday: day.format('YYYY-MM-DD') === today,
            isWeekend: day.day() === 0 || day.day() === 6,
        };
    });
});

function formatHoursMinutes(hours: number): string {
    if (hours === 0) return '-';
    const h = Math.floor(hours);
    const m = Math.round((hours - h) * 60);
    if (m === 0) return `${h}h`;
    if (h === 0) return `${m}m`;
    return `${h}h ${m}m`;
}

function handleCellUpdate(rowIndex: number, dayIndex: number, hours: number) {
    emit('updateCell', rowIndex, dayIndex, hours);
}

function getDayTotalClass(total: number): string {
    if (total === 0) return 'text-text-tertiary';
    if (total >= 8) return 'text-green-600 font-medium';
    return 'text-text-primary font-medium';
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px]">
            <thead>
                <tr class="border-b border-default-background-separator">
                    <th
                        class="py-2 px-4 text-left text-xs font-medium text-text-tertiary uppercase tracking-wider w-48">
                        Task
                    </th>
                    <th
                        v-for="day in weekDays"
                        :key="day.date"
                        class="py-2 px-2 text-center text-xs font-medium uppercase tracking-wider w-20"
                        :class="{
                            'text-primary-500': day.isToday,
                            'text-text-tertiary': !day.isToday,
                            'bg-tertiary/30': day.isWeekend,
                        }">
                        <div>{{ day.dayName }}</div>
                        <div
                            class="text-sm font-semibold"
                            :class="{
                                'text-primary-500': day.isToday,
                                'text-text-secondary': !day.isToday,
                            }">
                            {{ day.dayNumber }}
                        </div>
                    </th>
                    <th
                        class="py-2 px-4 text-right text-xs font-medium text-text-tertiary uppercase tracking-wider w-20">
                        Total
                    </th>
                </tr>
            </thead>

            <tbody>
                <tr
                    v-for="(row, rowIndex) in weekData.rows"
                    :key="row.id"
                    class="border-b border-default-background-separator hover:bg-tertiary/20 transition-colors">
                    <td class="py-1 px-4">
                        <TimesheetRowHeader
                            :project="row.project"
                            :task="row.task"
                            :is-new="row.isNew"
                            @remove="emit('removeRow', rowIndex)">
                        </TimesheetRowHeader>
                    </td>
                    <td
                        v-for="(cell, dayIndex) in row.cells"
                        :key="cell.date"
                        class="py-1 px-1"
                        :class="{
                            'bg-tertiary/30': weekDays[dayIndex]?.isWeekend,
                            'bg-primary-50/50': weekDays[dayIndex]?.isToday,
                        }">
                        <TimesheetCell
                            :cell="cell"
                            :is-loading="isLoading"
                            @update="
                                (hours: number) =>
                                    handleCellUpdate(rowIndex, dayIndex, hours)
                            ">
                        </TimesheetCell>
                    </td>
                    <td
                        class="py-2 px-4 text-right font-medium text-text-primary text-sm">
                        {{ formatHoursMinutes(row.total_hours) }}
                    </td>
                </tr>

                <tr v-if="weekData.rows.length === 0">
                    <td
                        colspan="9"
                        class="py-8 text-center text-text-tertiary">
                        <div class="text-sm">No time entries this week</div>
                        <div class="text-xs mt-1">
                            Add a task below to start tracking time
                        </div>
                    </td>
                </tr>
            </tbody>

            <tfoot>
                <tr
                    class="border-t-2 border-default-background-separator bg-tertiary/10">
                    <td class="py-3 px-4 font-medium text-text-primary text-sm">
                        Daily Total
                    </td>
                    <td
                        v-for="(total, index) in weekData.day_totals"
                        :key="index"
                        class="py-3 px-2 text-center text-sm"
                        :class="[
                            getDayTotalClass(total),
                            {
                                'bg-primary-50/50': weekDays[index]?.isToday,
                            },
                        ]">
                        {{ formatHoursMinutes(total) }}
                    </td>
                    <td
                        class="py-3 px-4 text-right font-bold text-text-primary text-sm">
                        {{ formatHoursMinutes(weekData.week_total) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</template>

<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import MainContainer from '@/packages/ui/src/MainContainer.vue';
import { onMounted } from 'vue';
import { useTimesheetStore } from '@/utils/useTimesheet';
import { storeToRefs } from 'pinia';
import { TableCellsIcon } from '@heroicons/vue/20/solid';
import LoadingSpinner from '@/packages/ui/src/LoadingSpinner.vue';
import TimesheetWeekAccordion from '@/packages/ui/src/Timesheet/TimesheetWeekAccordion.vue';

const timesheetStore = useTimesheetStore();
const { weekList, isLoadingList, hasMoreWeeks, expandedWeeks } =
    storeToRefs(timesheetStore);

onMounted(async () => {
    await Promise.all([timesheetStore.loadWeekList(), timesheetStore.loadRecentTasks()]);
});
</script>

<template>
    <AppLayout title="Timesheet" data-testid="timesheet_view">
        <MainContainer class="pt-5 lg:pt-8 pb-4 lg:pb-6">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <TableCellsIcon
                        class="w-6 h-6 text-icon-default"></TableCellsIcon>
                    <h1 class="text-xl font-semibold text-text-primary">
                        Timesheet
                    </h1>
                </div>
            </div>
        </MainContainer>

        <div v-if="isLoadingList && weekList.length === 0" class="flex justify-center py-12">
            <LoadingSpinner></LoadingSpinner>
            <span class="text-text-primary font-medium">Loading timesheet...</span>
        </div>

        <div v-else-if="weekList.length === 0" class="text-center pt-12">
            <TableCellsIcon class="w-8 text-icon-default inline pb-2"></TableCellsIcon>
            <h3 class="text-text-primary font-semibold">No time entries found</h3>
            <p class="pb-5 text-text-secondary">
                Start tracking time to see your weekly timesheet.
            </p>
        </div>

        <div v-else class="space-y-0">
            <TimesheetWeekAccordion
                v-for="week in weekList"
                :key="week.week_start"
                :week="week"
                :is-expanded="expandedWeeks.has(week.week_start)"
                @toggle="timesheetStore.toggleWeek(week.week_start)">
            </TimesheetWeekAccordion>

            <div v-if="hasMoreWeeks" class="flex justify-center py-4">
                <button
                    class="text-sm text-text-secondary hover:text-text-primary transition-colors"
                    :disabled="isLoadingList"
                    @click="timesheetStore.loadMoreWeeks()">
                    <span v-if="isLoadingList">Loading...</span>
                    <span v-else>Load older weeks</span>
                </button>
            </div>
        </div>
    </AppLayout>
</template>

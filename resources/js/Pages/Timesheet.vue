<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import TimesheetWeekNavigation from '@/Components/TimesheetGrid/TimesheetWeekNavigation.vue';
import TimesheetGrid from '@/Components/TimesheetGrid/TimesheetGrid.vue';
import {
    useTimesheetGridStore,
    useTimesheetGridQuery,
} from '@/utils/useTimesheetGrid';
import { computed } from 'vue';
import LoadingSpinner from '@/packages/ui/src/LoadingSpinner.vue';

defineOptions({ layout: AppLayout });

const _store = useTimesheetGridStore();
const { data: gridResponse, isLoading } = useTimesheetGridQuery();

const gridData = computed(() => gridResponse.value?.data ?? undefined);
</script>

<template>
    <AppLayout title="Timesheet" data-testid="timesheet_view">
        <div class="py-4 px-4 sm:px-6 lg:px-8">
            <TimesheetWeekNavigation />
            <div v-if="isLoading && !gridData" class="flex items-center justify-center py-20">
                <LoadingSpinner />
                <span class="text-text-secondary font-medium">
                    Loading timesheet...
                </span>
            </div>
            <TimesheetGrid
                v-else
                :data="gridData"
                :is-loading="isLoading" />
        </div>
    </AppLayout>
</template>

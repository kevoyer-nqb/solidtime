<script setup lang="ts">
import { ref } from 'vue';
import type { TimesheetGridResponse } from '@/utils/useTimesheetGrid';
import TimesheetGridHeader from './TimesheetGridHeader.vue';
import TimesheetGridRow from './TimesheetGridRow.vue';
import TimesheetGridTotals from './TimesheetGridTotals.vue';
import { PlusIcon } from '@heroicons/vue/20/solid';

defineProps<{
    data: TimesheetGridResponse | undefined;
    isLoading: boolean;
}>();

const isAddingRow = ref(false);

function startAddingRow() {
    isAddingRow.value = true;
}
</script>

<template>
    <div class="relative">
        <div
            v-if="isLoading"
            class="absolute inset-0 bg-default-background/50 z-10 flex items-center justify-center rounded-lg">
        </div>

        <div v-if="!data || data.rows.length === 0" class="text-center py-12">
            <p class="text-text-secondary">
                No time entries this week. Use the time tracker or add a row below to get started.
            </p>
        </div>

        <div v-if="data" class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <TimesheetGridHeader :days="data.days" />
                </thead>
                <tbody class="divide-y divide-default-background-separator">
                    <TimesheetGridRow
                        v-for="(row, index) in data.rows"
                        :key="`${row.project_id}-${row.task_id}-${index}`"
                        :row="row"
                        :days="data.days" />
                </tbody>
                <tfoot v-if="data.rows.length > 0">
                    <TimesheetGridTotals
                        :daily-totals="data.daily_totals"
                        :weekly-total="data.weekly_total" />
                </tfoot>
            </table>
        </div>

        <div class="mt-3">
            <button
                v-if="!isAddingRow"
                type="button"
                class="flex items-center gap-1 text-sm text-text-tertiary hover:text-text-primary transition-colors py-2 px-1"
                @click="startAddingRow">
                <PlusIcon class="w-4 h-4" />
                Add Row
            </button>
        </div>
    </div>
</template>

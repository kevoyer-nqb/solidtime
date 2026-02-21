<script setup lang="ts">
import { computed, ref } from 'vue';
import type { TimesheetGridResponse } from '@/utils/useTimesheetGrid';
import TimesheetGridHeader from './TimesheetGridHeader.vue';
import TimesheetGridRow from './TimesheetGridRow.vue';
import TimesheetGridTotals from './TimesheetGridTotals.vue';
import TimesheetGridCellComponent from './TimesheetGridCell.vue';
import type { TimesheetGridCell } from '@/utils/useTimesheetGrid';
import { useProjectsStore } from '@/utils/useProjects';
import { useTasksStore } from '@/utils/useTasks';
import { storeToRefs } from 'pinia';
import { PlusIcon, XMarkIcon } from '@heroicons/vue/20/solid';

defineProps<{
    data: TimesheetGridResponse | undefined;
    isLoading: boolean;
}>();

const projectsStore = useProjectsStore();
const { projects } = storeToRefs(projectsStore);
const tasksStore = useTasksStore();
const { tasks } = storeToRefs(tasksStore);

const isAddingRow = ref(false);
const newRowProjectId = ref<string | null>(null);
const newRowTaskId = ref<string | null>(null);

const filteredTasks = computed(() => {
    if (!newRowProjectId.value) return [];
    return tasks.value.filter(
        (t) => t.project_id === newRowProjectId.value && !t.is_done
    );
});

const activeProjects = computed(() => {
    return projects.value.filter((p) => !p.is_archived);
});

function startAddingRow() {
    isAddingRow.value = true;
    newRowProjectId.value = null;
    newRowTaskId.value = null;
}

function cancelAddingRow() {
    isAddingRow.value = false;
    newRowProjectId.value = null;
    newRowTaskId.value = null;
}

function onProjectChange() {
    newRowTaskId.value = null;
}

function makeEmptyCell(day: string): TimesheetGridCell {
    return { date: day, total_seconds: 0, time_entry_ids: [] };
}
</script>

<template>
    <div class="relative">
        <div
            v-if="isLoading"
            class="absolute inset-0 bg-default-background/50 z-10 flex items-center justify-center rounded-lg">
        </div>

        <div v-if="!data || (data.rows.length === 0 && !isAddingRow)" class="text-center py-12">
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

                    <!-- Add Row inline picker -->
                    <tr v-if="isAddingRow" class="bg-card-background/30">
                        <td class="py-2.5 px-3">
                            <div class="space-y-1.5">
                                <select
                                    v-model="newRowProjectId"
                                    class="w-full text-sm rounded-md border border-card-border bg-default-background text-text-primary px-2 py-1.5 focus:ring-1 focus:ring-accent-300 focus:border-accent-300"
                                    @change="onProjectChange">
                                    <option :value="null" disabled>Select project...</option>
                                    <option
                                        v-for="project in activeProjects"
                                        :key="project.id"
                                        :value="project.id">
                                        {{ project.name }}
                                    </option>
                                </select>
                                <select
                                    v-if="filteredTasks.length > 0"
                                    v-model="newRowTaskId"
                                    class="w-full text-sm rounded-md border border-card-border bg-default-background text-text-primary px-2 py-1.5 focus:ring-1 focus:ring-accent-300 focus:border-accent-300">
                                    <option :value="null">No task</option>
                                    <option
                                        v-for="task in filteredTasks"
                                        :key="task.id"
                                        :value="task.id">
                                        {{ task.name }}
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    class="flex items-center gap-1 text-xs text-text-tertiary hover:text-text-primary transition-colors"
                                    @click="cancelAddingRow">
                                    <XMarkIcon class="w-3.5 h-3.5" />
                                    Cancel
                                </button>
                            </div>
                        </td>
                        <td
                            v-for="day in data.days"
                            :key="day"
                            class="text-center py-2.5 px-1">
                            <TimesheetGridCellComponent
                                v-if="newRowProjectId"
                                :cell="makeEmptyCell(day)"
                                :project-id="newRowProjectId"
                                :task-id="newRowTaskId"
                                :date="day" />
                        </td>
                        <td class="text-center py-2.5 px-3">
                            <span class="text-sm text-text-tertiary">--</span>
                        </td>
                    </tr>
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

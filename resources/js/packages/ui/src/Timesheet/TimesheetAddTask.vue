<script setup lang="ts">
import { ref, computed } from 'vue';
import type { RecentTask } from '@/types/timesheet';
import { PlusIcon } from '@heroicons/vue/20/solid';

const props = defineProps<{
    recentTasks: RecentTask[];
}>();

const emit = defineEmits<{
    addTask: [projectId: string | null, taskId: string | null];
}>();

const isOpen = ref(false);
const searchQuery = ref('');

const filteredTasks = computed(() => {
    if (!searchQuery.value) return props.recentTasks;
    const query = searchQuery.value.toLowerCase();
    return props.recentTasks.filter((rt) => {
        const projectName = rt.project?.name?.toLowerCase() ?? '';
        const taskName = rt.task?.name?.toLowerCase() ?? '';
        return projectName.includes(query) || taskName.includes(query);
    });
});

function selectTask(task: RecentTask) {
    emit('addTask', task.project?.id ?? null, task.task?.id ?? null);
    isOpen.value = false;
    searchQuery.value = '';
}

function addNoProject() {
    emit('addTask', null, null);
    isOpen.value = false;
    searchQuery.value = '';
}

function handleClickOutside() {
    isOpen.value = false;
    searchQuery.value = '';
}
</script>

<template>
    <div class="relative">
        <button
            class="flex items-center gap-1 text-sm text-text-secondary hover:text-text-primary transition-colors"
            @click="isOpen = !isOpen">
            <PlusIcon class="w-4 h-4"></PlusIcon>
            <span>Add Task</span>
        </button>

        <!-- Dropdown -->
        <div
            v-if="isOpen"
            class="absolute left-0 top-full mt-1 w-72 bg-card-background border border-default-background-separator rounded-lg shadow-lg z-20"
            @focusout="handleClickOutside">
            <div class="p-2">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search projects and tasks..."
                    class="w-full px-3 py-1.5 text-sm border border-default-background-separator rounded focus:outline-none focus:ring-1 focus:ring-primary-500 bg-card-background text-text-primary" />
            </div>

            <div class="max-h-48 overflow-y-auto">
                <button
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-left hover:bg-tertiary/20 transition-colors"
                    @click="addNoProject">
                    <div
                        class="w-2 h-2 rounded-full bg-gray-400 flex-shrink-0"></div>
                    <span class="text-text-secondary">No Project</span>
                </button>

                <button
                    v-for="(task, index) in filteredTasks"
                    :key="index"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-left hover:bg-tertiary/20 transition-colors"
                    @click="selectTask(task)">
                    <div
                        class="w-2 h-2 rounded-full flex-shrink-0"
                        :style="{
                            backgroundColor: task.project?.color ?? '#9CA3AF',
                        }"></div>
                    <div class="min-w-0 flex-1">
                        <div class="text-text-primary truncate">
                            {{ task.project?.name ?? 'No Project' }}
                        </div>
                        <div
                            v-if="task.task"
                            class="text-xs text-text-secondary truncate">
                            {{ task.task.name }}
                        </div>
                    </div>
                </button>

                <div
                    v-if="filteredTasks.length === 0 && recentTasks.length > 0"
                    class="px-3 py-4 text-sm text-text-tertiary text-center">
                    No matching tasks found
                </div>

                <div
                    v-if="recentTasks.length === 0"
                    class="px-3 py-4 text-sm text-text-tertiary text-center">
                    No recent tasks available
                </div>
            </div>
        </div>
    </div>
</template>

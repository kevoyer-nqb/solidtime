<script setup lang="ts">
import { XMarkIcon } from '@heroicons/vue/20/solid';

defineProps<{
    project: {
        id: string;
        name: string;
        color: string;
    } | null;
    task: {
        id: string;
        name: string;
    } | null;
    isNew: boolean;
}>();

const emit = defineEmits<{
    remove: [];
}>();
</script>

<template>
    <div class="flex items-center gap-2 min-w-0 group">
        <div
            v-if="project"
            class="w-2 h-2 rounded-full flex-shrink-0"
            :style="{ backgroundColor: project.color }"></div>
        <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-text-primary truncate">
                {{ project?.name ?? 'No Project' }}
            </div>
            <div
                v-if="task"
                class="text-xs text-text-secondary truncate">
                {{ task.name }}
            </div>
        </div>
        <button
            v-if="isNew"
            class="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 rounded hover:bg-red-100"
            :aria-label="`Remove ${project?.name ?? 'No Project'} row`"
            @click="emit('remove')">
            <XMarkIcon class="w-4 h-4 text-red-500"></XMarkIcon>
        </button>
    </div>
</template>

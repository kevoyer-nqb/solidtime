<script setup lang="ts">
import { computed } from 'vue';
import { getDayJsInstance } from '@/packages/ui/src/utils/time';

defineProps<{
    days: string[];
}>();

const dayjs = getDayJsInstance();

const today = computed(() => {
    return dayjs().format('YYYY-MM-DD');
});

function formatDayHeader(day: string): string {
    return dayjs(day).format('ddd D');
}

function isToday(day: string): boolean {
    return day === today.value;
}
</script>

<template>
    <tr>
        <th
            class="text-left text-xs font-medium text-text-tertiary uppercase tracking-wider py-3 px-3 w-[30%]">
            Project / Task
        </th>
        <th
            v-for="day in days"
            :key="day"
            class="text-center text-xs font-medium text-text-tertiary uppercase tracking-wider py-3 px-2"
            :class="isToday(day) ? 'bg-accent-200/20 rounded-t-lg' : ''">
            {{ formatDayHeader(day) }}
        </th>
        <th
            class="text-center text-xs font-medium text-text-tertiary uppercase tracking-wider py-3 px-3 w-[10%]">
            Total
        </th>
    </tr>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useTimesheetGridStore } from '@/utils/useTimesheetGrid';
import { getDayJsInstance } from '@/packages/ui/src/utils/time';
import {
    ChevronLeftIcon,
    ChevronRightIcon,
} from '@heroicons/vue/20/solid';

const store = useTimesheetGridStore();

const dayjs = getDayJsInstance();

const weekStart = computed(() => {
    return dayjs(store.currentWeekDate).startOf('week');
});

const weekEnd = computed(() => {
    return weekStart.value.add(6, 'day');
});

const weekRangeLabel = computed(() => {
    const start = weekStart.value;
    const end = weekEnd.value;

    if (start.month() === end.month()) {
        // Same month: "Feb 9 - 15, 2026"
        return `${start.format('MMM D')} - ${end.format('D, YYYY')}`;
    }
    // Cross-month: "Jan 27 - Feb 2, 2026"
    if (start.year() === end.year()) {
        return `${start.format('MMM D')} - ${end.format('MMM D, YYYY')}`;
    }
    // Cross-year (rare): "Dec 29, 2025 - Jan 4, 2026"
    return `${start.format('MMM D, YYYY')} - ${end.format('MMM D, YYYY')}`;
});

const isCurrentWeek = computed(() => {
    const today = dayjs();
    const todayWeekStart = today.startOf('week');
    return weekStart.value.isSame(todayWeekStart, 'day');
});
</script>

<template>
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="p-1.5 rounded-lg hover:bg-card-background border border-transparent hover:border-card-border text-text-secondary hover:text-text-primary transition-colors"
                aria-label="Previous week"
                @click="store.goToPreviousWeek()">
                <ChevronLeftIcon class="w-5 h-5" />
            </button>
            <button
                type="button"
                class="p-1.5 rounded-lg hover:bg-card-background border border-transparent hover:border-card-border text-text-secondary hover:text-text-primary transition-colors"
                aria-label="Next week"
                @click="store.goToNextWeek()">
                <ChevronRightIcon class="w-5 h-5" />
            </button>
            <h2 class="text-lg font-semibold text-text-primary ml-2">
                {{ weekRangeLabel }}
            </h2>
        </div>
        <button
            type="button"
            class="px-3 py-1.5 text-sm font-medium rounded-lg border transition-colors"
            :class="
                isCurrentWeek
                    ? 'bg-card-background border-card-border text-text-tertiary cursor-default'
                    : 'border-card-border hover:bg-card-background text-text-secondary hover:text-text-primary'
            "
            :disabled="isCurrentWeek"
            @click="store.goToCurrentWeek()">
            Today
        </button>
    </div>
</template>

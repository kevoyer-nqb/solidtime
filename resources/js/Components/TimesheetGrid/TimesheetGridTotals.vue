<script setup lang="ts">
import { computed, inject, type ComputedRef } from 'vue';
import { formatHumanReadableDuration } from '@/packages/ui/src/utils/time';
import type { Organization } from '@/packages/api/src';

const props = defineProps<{
    dailyTotals: number[];
    weeklyTotal: number;
}>();

const organization = inject<ComputedRef<Organization>>('organization');

const intervalFormat = computed(
    () => organization?.value?.interval_format ?? 'hours-minutes'
);
const numberFormat = computed(
    () => organization?.value?.number_format ?? 'point'
);

function formatTotal(seconds: number): string {
    return formatHumanReadableDuration(
        seconds,
        intervalFormat.value,
        numberFormat.value
    );
}

const formattedWeeklyTotal = computed(() => {
    return formatTotal(props.weeklyTotal);
});
</script>

<template>
    <tr class="bg-card-background/50 border-t border-default-background-separator">
        <td class="py-2.5 px-3">
            <span class="text-sm font-semibold text-text-primary">Daily Total</span>
        </td>
        <td
            v-for="(total, index) in dailyTotals"
            :key="index"
            class="text-center py-2.5 px-1">
            <span class="text-sm font-semibold text-text-primary">
                {{ formatTotal(total) }}
            </span>
        </td>
        <td class="text-center py-2.5 px-3">
            <span class="text-sm font-bold text-text-primary">
                {{ formattedWeeklyTotal }}
            </span>
        </td>
    </tr>
</template>

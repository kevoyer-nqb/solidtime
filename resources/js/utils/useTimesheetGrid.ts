import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import dayjs from 'dayjs';
import { getCurrentOrganizationId, getCurrentMembershipId } from '@/utils/useUser';
import { getUserTimezone } from '@/packages/ui/src/utils/settings';
import { api } from '@/packages/api/src';
import { useNotificationsStore } from '@/utils/notification';

// --- Type definitions ---

export interface TimesheetGridResponse {
    week_start: string;
    week_end: string;
    days: string[];
    rows: TimesheetGridRow[];
    daily_totals: number[];
    weekly_total: number;
}

export interface TimesheetGridRow {
    project_id: string | null;
    project_name: string;
    project_color: string;
    task_id: string | null;
    task_name: string | null;
    cells: TimesheetGridCell[];
    row_total: number;
}

export interface TimesheetGridCell {
    date: string;
    total_seconds: number;
    time_entry_ids: string[];
}

// --- XSRF token helper (Phase 1 pattern from useNotificationBell.ts) ---

function getXsrfToken(): string | undefined {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : undefined;
}

async function fetchJson<T>(url: string, options?: RequestInit): Promise<T> {
    const xsrfToken = getXsrfToken();
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
            ...options?.headers,
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`HTTP error ${response.status}`);
    }

    return response.json();
}

// --- Pinia store ---

export const useTimesheetGridStore = defineStore('timesheetGrid', () => {
    const currentWeekDate = ref<string>(dayjs().format('YYYY-MM-DD'));
    const { handleApiRequestNotifications } = useNotificationsStore();
    const queryClient = useQueryClient();

    // Week navigation methods
    function goToPreviousWeek() {
        currentWeekDate.value = dayjs(currentWeekDate.value)
            .subtract(7, 'day')
            .format('YYYY-MM-DD');
    }

    function goToNextWeek() {
        currentWeekDate.value = dayjs(currentWeekDate.value)
            .add(7, 'day')
            .format('YYYY-MM-DD');
    }

    function goToCurrentWeek() {
        currentWeekDate.value = dayjs().format('YYYY-MM-DD');
    }

    // Invalidate grid data after edits
    function invalidateGrid() {
        queryClient.invalidateQueries({
            queryKey: ['timesheet-grid'],
        });
    }

    // Cell update: adjust existing time entries to match new total duration.
    // For single entry: adjusts that entry's end time.
    // For multiple entries: adjusts the last entry's end time by the delta.
    async function updateCellDuration(
        timeEntryIds: string[],
        newTotalSeconds: number,
        currentTotalSeconds: number
    ) {
        const organizationId = getCurrentOrganizationId();
        const memberId = getCurrentMembershipId();
        if (!organizationId || !memberId || timeEntryIds.length === 0) return;

        const delta = newTotalSeconds - currentTotalSeconds;
        if (delta === 0) return;

        const targetEntryId = timeEntryIds[timeEntryIds.length - 1];

        await handleApiRequestNotifications(
            async () => {
                // Fetch member's time entries to find the target entry's current times
                const response = await api.getTimeEntries({
                    params: { organization: organizationId },
                    queries: { member_id: memberId },
                });

                const targetEntry = response.data.find(
                    (e) => e.id === targetEntryId
                );

                if (!targetEntry || !targetEntry.end) return;

                const entryStart = dayjs(targetEntry.start);
                const entryEnd = dayjs(targetEntry.end);
                const entryDurationSeconds = entryEnd.diff(entryStart, 'second');
                const newEntryDuration = entryDurationSeconds + delta;

                if (newEntryDuration < 0) return;

                const newEnd = entryStart
                    .add(newEntryDuration, 'second')
                    .utc()
                    .format('YYYY-MM-DDTHH:mm:ss[Z]');

                await api.updateTimeEntry(
                    { end: newEnd },
                    {
                        params: {
                            organization: organizationId,
                            timeEntry: targetEntryId,
                        },
                    }
                );
            },
            'Duration updated successfully',
            'Failed to update duration'
        );

        invalidateGrid();
    }

    // Cell create: create a new time entry for an empty cell
    async function createCellEntry(
        projectId: string | null,
        taskId: string | null,
        date: string,
        durationSeconds: number
    ) {
        const organizationId = getCurrentOrganizationId();
        const memberId = getCurrentMembershipId();

        if (!organizationId || !memberId) return;

        const timezone = getUserTimezone();

        // Synthesize start time: 09:00 in user's timezone on the target date
        const localStart = dayjs.tz(`${date} 09:00:00`, timezone);
        const utcStart = localStart.utc().format('YYYY-MM-DDTHH:mm:ss[Z]');
        const utcEnd = localStart
            .add(durationSeconds, 'second')
            .utc()
            .format('YYYY-MM-DDTHH:mm:ss[Z]');

        await handleApiRequestNotifications(
            () =>
                api.createTimeEntry(
                    {
                        member_id: memberId,
                        project_id: projectId,
                        task_id: taskId,
                        start: utcStart,
                        end: utcEnd,
                        billable: false,
                        description: '',
                        tags: [],
                    },
                    {
                        params: {
                            organization: organizationId,
                        },
                    }
                ),
            'Time entry created successfully',
            'Failed to create time entry'
        );

        invalidateGrid();
    }

    return {
        currentWeekDate,
        goToPreviousWeek,
        goToNextWeek,
        goToCurrentWeek,
        invalidateGrid,
        updateCellDuration,
        createCellEntry,
    };
});

// --- TanStack Query composable (exported separately) ---

export function useTimesheetGridQuery() {
    const store = useTimesheetGridStore();

    return useQuery<{ data: TimesheetGridResponse }>({
        queryKey: computed(() => [
            'timesheet-grid',
            getCurrentOrganizationId(),
            store.currentWeekDate,
        ]),
        queryFn: () => {
            const orgId = getCurrentOrganizationId();
            return fetchJson<{ data: TimesheetGridResponse }>(
                `/api/v1/organizations/${orgId}/timesheet-grid?date=${store.currentWeekDate}`
            );
        },
        enabled: computed(() => !!getCurrentOrganizationId()),
    });
}

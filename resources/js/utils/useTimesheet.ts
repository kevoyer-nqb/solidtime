import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import axios from 'axios';
import type {
    WeekSummary,
    TimesheetWeekData,
    TimesheetRow,
    RecentTask,
} from '@/types/timesheet';
import { getCurrentMembershipId, getCurrentOrganizationId } from '@/utils/useUser';
import { useNotificationsStore } from '@/utils/notification';

function getApiBase() {
    const orgId = getCurrentOrganizationId();
    return `/api/v1/organizations/${orgId}/timesheet`;
}

export const useTimesheetStore = defineStore('timesheet', () => {
    const weekList = ref<WeekSummary[]>([]);
    const expandedWeeks = ref<Set<string>>(new Set());
    const weekDataMap = ref<Map<string, TimesheetWeekData>>(new Map());
    const hasMoreWeeks = ref(true);
    const isLoadingList = ref(false);
    const loadingWeeks = ref<Set<string>>(new Set());
    const compactView = ref(false);
    const error = ref<string | null>(null);
    const recentTasks = ref<RecentTask[]>([]);

    const { handleApiRequestNotifications } = useNotificationsStore();

    async function loadWeekList() {
        isLoadingList.value = true;
        error.value = null;

        try {
            const response = await handleApiRequestNotifications(
                () =>
                    axios.get(getApiBase() + '/weeks', {
                        params: { limit: 8, offset: 0 },
                    }),
                undefined,
                'Failed to load week list'
            );
            if (response?.data?.data) {
                weekList.value = response.data.data;
                hasMoreWeeks.value = weekList.value.length >= 8;

                // Auto-expand current week
                if (weekList.value.length > 0) {
                    const currentWeek = weekList.value[0].week_start;
                    if (!expandedWeeks.value.has(currentWeek)) {
                        await toggleWeek(currentWeek);
                    }
                }
            }
        } catch {
            error.value = 'Failed to load week list';
        } finally {
            isLoadingList.value = false;
        }
    }

    async function loadMoreWeeks() {
        if (!hasMoreWeeks.value || isLoadingList.value) return;

        isLoadingList.value = true;
        try {
            const response = await handleApiRequestNotifications(
                () =>
                    axios.get(getApiBase() + '/weeks', {
                        params: { limit: 8, offset: weekList.value.length },
                    }),
                undefined,
                'Failed to load more weeks'
            );
            if (response?.data?.data) {
                const newWeeks: WeekSummary[] = response.data.data;
                weekList.value = [...weekList.value, ...newWeeks];
                hasMoreWeeks.value = newWeeks.length >= 8;
            }
        } catch {
            // Error handled by notification store
        } finally {
            isLoadingList.value = false;
        }
    }

    async function loadWeekGrid(weekStart: string) {
        const weekSummary = weekList.value.find((w) => w.week_start === weekStart);
        if (!weekSummary) return;

        loadingWeeks.value.add(weekStart);
        try {
            const response = await handleApiRequestNotifications(
                () =>
                    axios.get(getApiBase(), {
                        params: {
                            week_start: weekSummary.week_start,
                            week_end: weekSummary.week_end,
                        },
                    }),
                undefined,
                'Failed to load week data'
            );
            if (response?.data?.data) {
                const data = response.data.data;
                // Add client-side properties to rows
                const rows: TimesheetRow[] = data.rows.map(
                    (row: TimesheetRow) =>
                        ({
                            ...row,
                            isNew: false,
                            cells: row.cells.map((cell) => ({
                                ...cell,
                                isEditing: false,
                                isLoading: false,
                                hasError: false,
                            })),
                        }) as TimesheetRow
                );

                weekDataMap.value.set(weekStart, {
                    week_start: data.week_start,
                    week_end: data.week_end,
                    rows,
                    day_totals: data.day_totals,
                    week_total: data.week_total,
                });
            }
        } catch {
            // Error handled by notification store
        } finally {
            loadingWeeks.value.delete(weekStart);
        }
    }

    async function toggleWeek(weekStart: string) {
        if (expandedWeeks.value.has(weekStart)) {
            expandedWeeks.value.delete(weekStart);
        } else {
            expandedWeeks.value.add(weekStart);
            // Lazy-load grid data if not already loaded
            if (!weekDataMap.value.has(weekStart)) {
                await loadWeekGrid(weekStart);
            }
        }
    }

    function toggleCompactView() {
        compactView.value = !compactView.value;
    }

    async function updateCell(
        weekStart: string,
        rowIndex: number,
        dayIndex: number,
        hours: number
    ) {
        const weekData = weekDataMap.value.get(weekStart);
        if (!weekData || !weekData.rows[rowIndex]) return;

        const row = weekData.rows[rowIndex];
        const cell = row.cells[dayIndex];
        const previousHours = cell.hours;

        // Optimistic update
        cell.isLoading = true;
        cell.hours = hours;
        cell.hasError = false;
        recalculateTotals(weekStart);

        const memberId = getCurrentMembershipId();
        if (!memberId) {
            cell.isLoading = false;
            cell.hours = previousHours;
            cell.hasError = true;
            recalculateTotals(weekStart);
            return;
        }

        try {
            const response = await handleApiRequestNotifications(
                () =>
                    axios.put(getApiBase() + '/cell', {
                        member_id: memberId,
                        date: cell.date,
                        project_id: row.project?.id ?? null,
                        task_id: row.task?.id ?? null,
                        hours: hours,
                    }),
                undefined,
                'Failed to update cell'
            );

            if (response?.data?.data) {
                cell.time_entry_ids = response.data.data.time_entry_ids;
                cell.hours = response.data.data.hours;
            }
        } catch {
            // Rollback optimistic update
            cell.hours = previousHours;
            cell.hasError = true;
        } finally {
            cell.isLoading = false;
            recalculateTotals(weekStart);
        }
    }

    function addTaskRow(weekStart: string, projectId: string | null, taskId: string | null) {
        const weekData = weekDataMap.value.get(weekStart);
        if (!weekData) return;

        // Check if row already exists
        const exists = weekData.rows.some(
            (row) =>
                (row.project?.id ?? null) === projectId && (row.task?.id ?? null) === taskId
        );
        if (exists) return;

        // Find project and task details from recent tasks
        const recentTask = recentTasks.value.find(
            (rt) =>
                (rt.project?.id ?? null) === projectId && (rt.task?.id ?? null) === taskId
        );

        const newRow: TimesheetRow = {
            id: `new-${Date.now()}`,
            project: recentTask?.project ?? null,
            task: recentTask?.task ?? null,
            cells: weekData.day_totals.map((_, i) => {
                const dayDate = new Date(weekData.week_start);
                dayDate.setDate(dayDate.getDate() + i);
                return {
                    date: dayDate.toISOString().split('T')[0],
                    hours: 0,
                    time_entry_ids: [],
                    isEditing: false,
                    isLoading: false,
                    hasError: false,
                };
            }),
            total_hours: 0,
            isNew: true,
        };

        weekData.rows.push(newRow);
    }

    async function loadRecentTasks() {
        try {
            const response = await handleApiRequestNotifications(
                () =>
                    axios.get(getApiBase() + '/recent-tasks', {
                        params: { limit: 10 },
                    }),
                undefined,
                'Failed to load recent tasks'
            );
            if (response?.data?.data) {
                recentTasks.value = response.data.data;
            }
        } catch {
            // Error handled by notification store
        }
    }

    function recalculateTotals(weekStart: string) {
        const weekData = weekDataMap.value.get(weekStart);
        if (!weekData) return;

        // Recalculate row totals
        for (const row of weekData.rows) {
            row.total_hours = Math.round(row.cells.reduce((sum, cell) => sum + cell.hours, 0) * 100) / 100;
        }

        // Recalculate day totals
        for (let i = 0; i < 7; i++) {
            weekData.day_totals[i] =
                Math.round(
                    weekData.rows.reduce((sum, row) => sum + (row.cells[i]?.hours ?? 0), 0) *
                        100
                ) / 100;
        }

        weekData.week_total =
            Math.round(weekData.day_totals.reduce((sum, total) => sum + total, 0) * 100) /
            100;
    }

    const grandTotal = computed(() => {
        return weekList.value.reduce((sum, week) => sum + week.total_seconds, 0);
    });

    return {
        weekList,
        expandedWeeks,
        weekDataMap,
        hasMoreWeeks,
        isLoadingList,
        loadingWeeks,
        compactView,
        error,
        recentTasks,
        grandTotal,
        loadWeekList,
        loadMoreWeeks,
        loadWeekGrid,
        toggleWeek,
        toggleCompactView,
        updateCell,
        addTaskRow,
        loadRecentTasks,
    };
});

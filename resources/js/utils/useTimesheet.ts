import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '@/packages/api/src';
import type {
    TimesheetWeekSummary,
    TimesheetRecentTask,
} from '@/packages/api/src';
import type {
    TimesheetWeekData,
    TimesheetRow,
} from '@/types/timesheet';
import { getCurrentMembershipId, getCurrentOrganizationId } from '@/utils/useUser';
import { useNotificationsStore } from '@/utils/notification';

export const useTimesheetStore = defineStore('timesheet', () => {
    const weekList = ref<TimesheetWeekSummary[]>([]);
    const expandedWeeks = ref<Set<string>>(new Set());
    const weekDataMap = ref<Map<string, TimesheetWeekData>>(new Map());
    const hasMoreWeeks = ref(true);
    const isLoadingList = ref(false);
    const loadingWeeks = ref<Set<string>>(new Set());
    const compactView = ref(false);
    const error = ref<string | null>(null);
    const recentTasks = ref<TimesheetRecentTask[]>([]);

    const { handleApiRequestNotifications } = useNotificationsStore();

    async function loadWeekList() {
        const organizationId = getCurrentOrganizationId();
        if (!organizationId) return;

        isLoadingList.value = true;
        error.value = null;

        try {
            const response = await handleApiRequestNotifications(
                () =>
                    api.getTimesheetWeeks({
                        params: { organization: organizationId },
                        queries: { limit: 8, offset: 0 },
                    }),
                undefined,
                'Failed to load week list'
            );
            if (response?.data) {
                weekList.value = response.data;
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
        const organizationId = getCurrentOrganizationId();
        if (!organizationId || !hasMoreWeeks.value || isLoadingList.value) return;

        isLoadingList.value = true;
        try {
            const response = await handleApiRequestNotifications(
                () =>
                    api.getTimesheetWeeks({
                        params: { organization: organizationId },
                        queries: { limit: 8, offset: weekList.value.length },
                    }),
                undefined,
                'Failed to load more weeks'
            );
            if (response?.data) {
                const newWeeks = response.data;
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
        const organizationId = getCurrentOrganizationId();
        if (!organizationId) return;

        const weekSummary = weekList.value.find((w) => w.week_start === weekStart);
        if (!weekSummary) return;

        loadingWeeks.value.add(weekStart);
        try {
            const response = await handleApiRequestNotifications(
                () =>
                    api.getTimesheetGrid({
                        params: { organization: organizationId },
                        queries: {
                            week_start: weekSummary.week_start,
                            week_end: weekSummary.week_end,
                        },
                    }),
                undefined,
                'Failed to load week data'
            );
            if (response?.data) {
                const data = response.data;
                // Add client-side properties to rows
                const rows: TimesheetRow[] = data.rows.map(
                    (row) =>
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
        const organizationId = getCurrentOrganizationId();
        if (!organizationId) return;

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
                    api.updateTimesheetCell(
                        {
                            member_id: memberId,
                            date: cell.date,
                            project_id: row.project?.id ?? null,
                            task_id: row.task?.id ?? null,
                            hours: hours,
                        },
                        {
                            params: { organization: organizationId },
                        }
                    ),
                undefined,
                'Failed to update cell'
            );

            if (response?.data) {
                cell.time_entry_ids = response.data.time_entry_ids;
                cell.hours = response.data.hours;
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
        const organizationId = getCurrentOrganizationId();
        if (!organizationId) return;

        try {
            const response = await handleApiRequestNotifications(
                () =>
                    api.getTimesheetRecentTasks({
                        params: { organization: organizationId },
                        queries: { limit: 10 },
                    }),
                undefined,
                'Failed to load recent tasks'
            );
            if (response?.data) {
                recentTasks.value = response.data;
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

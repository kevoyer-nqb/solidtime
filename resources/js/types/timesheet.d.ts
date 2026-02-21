import type {
    TimesheetCell as ApiTimesheetCell,
    TimesheetRow as ApiTimesheetRow,
} from '@/packages/api/src';

// Re-export API types that are used directly
export type { TimesheetWeekSummary as WeekSummary } from '@/packages/api/src';
export type { TimesheetRecentTask as RecentTask } from '@/packages/api/src';

// Client-side cell extends the API cell with UI state
export interface TimesheetCell extends ApiTimesheetCell {
    isEditing: boolean;
    isLoading: boolean;
    hasError: boolean;
}

// Client-side row extends the API row with UI state and overrides cells type
export interface TimesheetRow extends Omit<ApiTimesheetRow, 'cells'> {
    cells: TimesheetCell[];
    isNew: boolean;
}

export interface TimesheetWeekData {
    week_start: string;
    week_end: string;
    rows: TimesheetRow[];
    day_totals: number[];
    week_total: number;
}

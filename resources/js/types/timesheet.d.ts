export interface WeekSummary {
    week_start: string;
    week_end: string;
    label: string;
    total_seconds: number;
}

export interface TimesheetCell {
    date: string;
    hours: number;
    time_entry_ids: string[];
    isEditing: boolean;
    isLoading: boolean;
    hasError: boolean;
}

export interface TimesheetRow {
    id: string;
    project: {
        id: string;
        name: string;
        color: string;
    } | null;
    task: {
        id: string;
        name: string;
    } | null;
    cells: TimesheetCell[];
    total_hours: number;
    isNew: boolean;
}

export interface TimesheetWeekData {
    week_start: string;
    week_end: string;
    rows: TimesheetRow[];
    day_totals: number[];
    week_total: number;
}

export interface RecentTask {
    project: {
        id: string;
        name: string;
        color: string;
    } | null;
    task: {
        id: string;
        name: string;
    } | null;
}

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import TimesheetGrid from '../TimesheetGrid.vue';
import type { TimesheetWeekData } from '@/types/timesheet';

function createWeekData(overrides: Partial<TimesheetWeekData> = {}): TimesheetWeekData {
    return {
        week_start: '2026-02-02',
        week_end: '2026-02-08',
        rows: [
            {
                id: 'p1:t1',
                project: { id: 'p1', name: 'Website', color: '#3B82F6' },
                task: { id: 't1', name: 'Frontend' },
                cells: [
                    { date: '2026-02-02', hours: 2, time_entry_ids: ['e1'], isEditing: false, isLoading: false, hasError: false },
                    { date: '2026-02-03', hours: 3, time_entry_ids: ['e2'], isEditing: false, isLoading: false, hasError: false },
                    { date: '2026-02-04', hours: 0, time_entry_ids: [], isEditing: false, isLoading: false, hasError: false },
                    { date: '2026-02-05', hours: 0, time_entry_ids: [], isEditing: false, isLoading: false, hasError: false },
                    { date: '2026-02-06', hours: 1.5, time_entry_ids: ['e3'], isEditing: false, isLoading: false, hasError: false },
                    { date: '2026-02-07', hours: 0, time_entry_ids: [], isEditing: false, isLoading: false, hasError: false },
                    { date: '2026-02-08', hours: 0, time_entry_ids: [], isEditing: false, isLoading: false, hasError: false },
                ],
                total_hours: 6.5,
                isNew: false,
            },
        ],
        day_totals: [2, 3, 0, 0, 1.5, 0, 0],
        week_total: 6.5,
        ...overrides,
    };
}

describe('TimesheetGrid', () => {
    it('renders table with correct structure', () => {
        const wrapper = mount(TimesheetGrid, {
            props: { weekData: createWeekData(), isLoading: false },
        });

        expect(wrapper.find('table').exists()).toBe(true);
        expect(wrapper.find('[role="grid"]').exists()).toBe(true);
    });

    it('renders 7 day headers plus Task and Total columns', () => {
        const wrapper = mount(TimesheetGrid, {
            props: { weekData: createWeekData(), isLoading: false },
        });

        const headers = wrapper.findAll('th');
        expect(headers.length).toBe(9); // Task + 7 days + Total
    });

    it('renders rows for each entry', () => {
        const wrapper = mount(TimesheetGrid, {
            props: { weekData: createWeekData(), isLoading: false },
        });

        const rows = wrapper.findAll('tbody tr');
        expect(rows.length).toBe(1);
    });

    it('displays row total', () => {
        const wrapper = mount(TimesheetGrid, {
            props: { weekData: createWeekData(), isLoading: false },
        });

        // Row total is 6.5 hours = "6h 30m"
        expect(wrapper.text()).toContain('6h 30m');
    });

    it('displays daily totals in footer', () => {
        const wrapper = mount(TimesheetGrid, {
            props: { weekData: createWeekData(), isLoading: false },
        });

        const footer = wrapper.find('tfoot');
        expect(footer.text()).toContain('Daily Total');
        expect(footer.text()).toContain('2h');
        expect(footer.text()).toContain('3h');
        expect(footer.text()).toContain('1h 30m');
    });

    it('displays week total in footer', () => {
        const wrapper = mount(TimesheetGrid, {
            props: { weekData: createWeekData(), isLoading: false },
        });

        const footer = wrapper.find('tfoot');
        expect(footer.text()).toContain('6h 30m');
    });

    it('shows empty state when no rows', () => {
        const wrapper = mount(TimesheetGrid, {
            props: {
                weekData: createWeekData({ rows: [] }),
                isLoading: false,
            },
        });

        expect(wrapper.text()).toContain('No time entries this week');
    });

    it('emits updateCell event', async () => {
        const wrapper = mount(TimesheetGrid, {
            props: { weekData: createWeekData(), isLoading: false },
        });

        // Find a cell and trigger its update
        const cellComponents = wrapper.findAllComponents({ name: 'TimesheetCell' });
        expect(cellComponents.length).toBeGreaterThan(0);

        cellComponents[0].vm.$emit('update', 4);
        expect(wrapper.emitted('updateCell')).toBeTruthy();
        expect(wrapper.emitted('updateCell')![0]).toEqual([0, 0, 4]);
    });

    it('emits removeRow event', async () => {
        const weekData = createWeekData();
        weekData.rows[0].isNew = true;
        const wrapper = mount(TimesheetGrid, {
            props: { weekData, isLoading: false },
        });

        const rowHeader = wrapper.findComponent({ name: 'TimesheetRowHeader' });
        rowHeader.vm.$emit('remove');
        expect(wrapper.emitted('removeRow')).toBeTruthy();
        expect(wrapper.emitted('removeRow')![0]).toEqual([0]);
    });

    it('formats hours correctly', () => {
        const weekData = createWeekData();
        weekData.rows[0].total_hours = 0;
        const wrapper = mount(TimesheetGrid, {
            props: { weekData, isLoading: false },
        });

        // 0 hours should show as "-"
        const totalCells = wrapper.findAll('tbody td:last-child');
        expect(totalCells[0].text()).toBe('-');
    });

    it('has grid role and aria-label', () => {
        const wrapper = mount(TimesheetGrid, {
            props: { weekData: createWeekData(), isLoading: false },
        });

        expect(wrapper.find('[role="grid"]').exists()).toBe(true);
        expect(wrapper.find('[role="grid"]').attributes('aria-label')).toContain('timesheet');
    });

    it('renders gridcell roles on cell containers', () => {
        const wrapper = mount(TimesheetGrid, {
            props: { weekData: createWeekData(), isLoading: false },
        });

        const gridcells = wrapper.findAll('[role="gridcell"]');
        expect(gridcells.length).toBe(7); // 7 cells per row, 1 row
    });
});

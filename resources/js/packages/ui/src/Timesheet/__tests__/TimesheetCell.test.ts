import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import TimesheetCell from '../TimesheetCell.vue';

function createCell(overrides = {}) {
    return {
        date: '2026-02-02',
        hours: 0,
        time_entry_ids: [],
        isEditing: false,
        isLoading: false,
        hasError: false,
        ...overrides,
    };
}

describe('TimesheetCell', () => {
    it('displays dash when hours is 0', () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell(), isLoading: false },
        });
        expect(wrapper.text()).toContain('-');
    });

    it('displays hours when > 0', () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell({ hours: 2 }), isLoading: false },
        });
        expect(wrapper.text()).toContain('2');
    });

    it('displays decimal hours correctly', () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell({ hours: 1.5 }), isLoading: false },
        });
        expect(wrapper.text()).toContain('1.5');
    });

    it('enters edit mode on click', async () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell(), isLoading: false },
        });

        await wrapper.find('[role="button"]').trigger('click');
        expect(wrapper.find('input').exists()).toBe(true);
    });

    it('emits update on Enter key', async () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell(), isLoading: false },
        });

        await wrapper.find('[role="button"]').trigger('click');
        const input = wrapper.find('input');
        await input.setValue('2.5');
        await input.trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('update')).toBeTruthy();
        expect(wrapper.emitted('update')![0]).toEqual([2.5]);
    });

    it('emits navigate down on Enter key', async () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell(), isLoading: false },
        });

        await wrapper.find('[role="button"]').trigger('click');
        const input = wrapper.find('input');
        await input.setValue('1');
        await input.trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('navigate')).toBeTruthy();
        expect(wrapper.emitted('navigate')![0]).toEqual(['down']);
    });

    it('cancels editing on Escape', async () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell({ hours: 2 }), isLoading: false },
        });

        await wrapper.find('[role="button"]').trigger('click');
        const input = wrapper.find('input');
        await input.setValue('99');
        await input.trigger('keydown', { key: 'Escape' });

        expect(wrapper.find('input').exists()).toBe(false);
        expect(wrapper.emitted('update')).toBeFalsy();
    });

    it('parses colon format (h:mm)', async () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell(), isLoading: false },
        });

        await wrapper.find('[role="button"]').trigger('click');
        const input = wrapper.find('input');
        await input.setValue('1:30');
        await input.trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('update')).toBeTruthy();
        expect(wrapper.emitted('update')![0]).toEqual([1.5]);
    });

    it('shows error for invalid input', async () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell(), isLoading: false },
        });

        await wrapper.find('[role="button"]').trigger('click');
        const input = wrapper.find('input');
        await input.setValue('abc');
        await input.trigger('keydown', { key: 'Enter' });

        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        expect(wrapper.find('[role="alert"]').text()).toContain('valid number');
    });

    it('shows error for hours > 24', async () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell(), isLoading: false },
        });

        await wrapper.find('[role="button"]').trigger('click');
        const input = wrapper.find('input');
        await input.setValue('25');
        await input.trigger('keydown', { key: 'Enter' });

        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        expect(wrapper.find('[role="alert"]').text()).toContain('24 hours');
    });

    it('shows error for negative hours', async () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell(), isLoading: false },
        });

        await wrapper.find('[role="button"]').trigger('click');
        const input = wrapper.find('input');
        await input.setValue('-1');
        await input.trigger('keydown', { key: 'Enter' });

        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        expect(wrapper.find('[role="alert"]').text()).toContain('negative');
    });

    it('shows error for minutes > 59 in colon format', async () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell(), isLoading: false },
        });

        await wrapper.find('[role="button"]').trigger('click');
        const input = wrapper.find('input');
        await input.setValue('1:60');
        await input.trigger('keydown', { key: 'Enter' });

        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        expect(wrapper.find('[role="alert"]').text()).toContain('0-59');
    });

    it('does not enter edit mode when loading', async () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell({ isLoading: true }), isLoading: false },
        });

        await wrapper.find('[role="button"]').trigger('click');
        expect(wrapper.find('input').exists()).toBe(false);
    });

    it('does not emit update when value unchanged', async () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell({ hours: 2 }), isLoading: false },
        });

        await wrapper.find('[role="button"]').trigger('click');
        const input = wrapper.find('input');
        // Don't change the value, just save
        await input.trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('update')).toBeFalsy();
    });

    it('emits 0 hours when clearing a cell', async () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell({ hours: 2 }), isLoading: false },
        });

        await wrapper.find('[role="button"]').trigger('click');
        const input = wrapper.find('input');
        await input.setValue('');
        await input.trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('update')).toBeTruthy();
        expect(wrapper.emitted('update')![0]).toEqual([0]);
    });

    it('navigates with arrow keys from display mode', async () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell(), isLoading: false },
        });

        const display = wrapper.find('[role="button"]');
        await display.trigger('keydown', { key: 'ArrowUp' });
        expect(wrapper.emitted('navigate')![0]).toEqual(['up']);

        await display.trigger('keydown', { key: 'ArrowDown' });
        expect(wrapper.emitted('navigate')![1]).toEqual(['down']);

        await display.trigger('keydown', { key: 'ArrowLeft' });
        expect(wrapper.emitted('navigate')![2]).toEqual(['left']);

        await display.trigger('keydown', { key: 'ArrowRight' });
        expect(wrapper.emitted('navigate')![3]).toEqual(['right']);
    });

    it('enters edit mode on Enter/Space from display mode', async () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell(), isLoading: false },
        });

        const display = wrapper.find('[role="button"]');
        await display.trigger('keydown', { key: 'Enter' });
        expect(wrapper.find('input').exists()).toBe(true);
    });

    it('has aria-label with hours and date', () => {
        const wrapper = mount(TimesheetCell, {
            props: { cell: createCell({ hours: 3 }), isLoading: false },
        });

        const display = wrapper.find('[role="button"]');
        expect(display.attributes('aria-label')).toContain('3 hours');
        expect(display.attributes('aria-label')).toContain('2026-02-02');
    });
});

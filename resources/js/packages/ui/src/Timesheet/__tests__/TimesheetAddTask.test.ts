import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import TimesheetAddTask from '../TimesheetAddTask.vue';

const recentTasks = [
    {
        project: { id: 'p1', name: 'Website Redesign', color: '#3B82F6' },
        task: { id: 't1', name: 'Frontend Dev' },
    },
    {
        project: { id: 'p2', name: 'Mobile App', color: '#10B981' },
        task: null,
    },
    {
        project: null,
        task: null,
    },
];

describe('TimesheetAddTask', () => {
    it('renders Add Task button', () => {
        const wrapper = mount(TimesheetAddTask, {
            props: { recentTasks: [] },
        });

        expect(wrapper.text()).toContain('Add Task');
    });

    it('opens dropdown on button click', async () => {
        const wrapper = mount(TimesheetAddTask, {
            props: { recentTasks },
        });

        await wrapper.find('button').trigger('click');
        expect(wrapper.find('input[placeholder]').exists()).toBe(true);
    });

    it('displays recent tasks in dropdown', async () => {
        const wrapper = mount(TimesheetAddTask, {
            props: { recentTasks },
        });

        await wrapper.find('button').trigger('click');
        expect(wrapper.text()).toContain('Website Redesign');
        expect(wrapper.text()).toContain('Frontend Dev');
        expect(wrapper.text()).toContain('Mobile App');
    });

    it('always shows No Project option', async () => {
        const wrapper = mount(TimesheetAddTask, {
            props: { recentTasks },
        });

        await wrapper.find('button').trigger('click');
        expect(wrapper.text()).toContain('No Project');
    });

    it('filters tasks by search query', async () => {
        const wrapper = mount(TimesheetAddTask, {
            props: { recentTasks },
        });

        await wrapper.find('button').trigger('click');
        const input = wrapper.find('input');
        await input.setValue('Mobile');

        expect(wrapper.text()).toContain('Mobile App');
        expect(wrapper.text()).not.toContain('Website Redesign');
    });

    it('shows "No matching tasks" when filter has no results', async () => {
        const wrapper = mount(TimesheetAddTask, {
            props: { recentTasks },
        });

        await wrapper.find('button').trigger('click');
        const input = wrapper.find('input');
        await input.setValue('NonExistent');

        expect(wrapper.text()).toContain('No matching tasks');
    });

    it('shows "No recent tasks" when recentTasks is empty', async () => {
        const wrapper = mount(TimesheetAddTask, {
            props: { recentTasks: [] },
        });

        await wrapper.find('button').trigger('click');
        expect(wrapper.text()).toContain('No recent tasks');
    });

    it('emits addTask with project and task IDs when task is selected', async () => {
        const wrapper = mount(TimesheetAddTask, {
            props: { recentTasks },
        });

        await wrapper.find('button').trigger('click');
        // Click on the first recent task (index 1 because index 0 is "No Project")
        const taskButtons = wrapper.findAll('.max-h-48 button');
        await taskButtons[1].trigger('click'); // Website Redesign

        expect(wrapper.emitted('addTask')).toBeTruthy();
        expect(wrapper.emitted('addTask')![0]).toEqual(['p1', 't1']);
    });

    it('emits addTask with nulls when No Project is selected', async () => {
        const wrapper = mount(TimesheetAddTask, {
            props: { recentTasks },
        });

        await wrapper.find('button').trigger('click');
        const taskButtons = wrapper.findAll('.max-h-48 button');
        await taskButtons[0].trigger('click'); // No Project

        expect(wrapper.emitted('addTask')).toBeTruthy();
        expect(wrapper.emitted('addTask')![0]).toEqual([null, null]);
    });

    it('closes dropdown after selecting a task', async () => {
        const wrapper = mount(TimesheetAddTask, {
            props: { recentTasks },
        });

        await wrapper.find('button').trigger('click');
        expect(wrapper.find('input[placeholder]').exists()).toBe(true);

        const taskButtons = wrapper.findAll('.max-h-48 button');
        await taskButtons[0].trigger('click');

        expect(wrapper.find('input[placeholder]').exists()).toBe(false);
    });

    it('clears search query after selecting a task', async () => {
        const wrapper = mount(TimesheetAddTask, {
            props: { recentTasks },
        });

        await wrapper.find('button').trigger('click');
        await wrapper.find('input').setValue('Web');

        const taskButtons = wrapper.findAll('.max-h-48 button');
        await taskButtons[1].trigger('click');

        // Reopen
        await wrapper.find('button').trigger('click');
        expect((wrapper.find('input').element as HTMLInputElement).value).toBe('');
    });
});

import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import TimesheetRowHeader from '../TimesheetRowHeader.vue';

describe('TimesheetRowHeader', () => {
    it('displays project name and color dot', () => {
        const wrapper = mount(TimesheetRowHeader, {
            props: {
                project: { id: '1', name: 'Website Redesign', color: '#3B82F6' },
                task: null,
                isNew: false,
            },
        });

        expect(wrapper.text()).toContain('Website Redesign');
        const dot = wrapper.find('[style]');
        expect(dot.attributes('style')).toContain('#3B82F6');
    });

    it('displays "No Project" when project is null', () => {
        const wrapper = mount(TimesheetRowHeader, {
            props: {
                project: null,
                task: null,
                isNew: false,
            },
        });

        expect(wrapper.text()).toContain('No Project');
    });

    it('displays task name when provided', () => {
        const wrapper = mount(TimesheetRowHeader, {
            props: {
                project: { id: '1', name: 'Project', color: '#000' },
                task: { id: '2', name: 'Frontend Development' },
                isNew: false,
            },
        });

        expect(wrapper.text()).toContain('Frontend Development');
    });

    it('does not display task section when task is null', () => {
        const wrapper = mount(TimesheetRowHeader, {
            props: {
                project: { id: '1', name: 'Project', color: '#000' },
                task: null,
                isNew: false,
            },
        });

        const taskEl = wrapper.findAll('.text-xs');
        expect(taskEl.length).toBe(0);
    });

    it('shows remove button when isNew is true', () => {
        const wrapper = mount(TimesheetRowHeader, {
            props: {
                project: { id: '1', name: 'Project', color: '#000' },
                task: null,
                isNew: true,
            },
        });

        expect(wrapper.find('button').exists()).toBe(true);
    });

    it('hides remove button when isNew is false', () => {
        const wrapper = mount(TimesheetRowHeader, {
            props: {
                project: { id: '1', name: 'Project', color: '#000' },
                task: null,
                isNew: false,
            },
        });

        expect(wrapper.find('button').exists()).toBe(false);
    });

    it('emits remove event when remove button is clicked', async () => {
        const wrapper = mount(TimesheetRowHeader, {
            props: {
                project: { id: '1', name: 'Project', color: '#000' },
                task: null,
                isNew: true,
            },
        });

        await wrapper.find('button').trigger('click');
        expect(wrapper.emitted('remove')).toBeTruthy();
    });

    it('has accessible label on remove button', () => {
        const wrapper = mount(TimesheetRowHeader, {
            props: {
                project: { id: '1', name: 'My Project', color: '#000' },
                task: null,
                isNew: true,
            },
        });

        expect(wrapper.find('button').attributes('aria-label')).toContain('My Project');
    });
});

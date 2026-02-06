import { config } from '@vue/test-utils';

// Stub router-link for Inertia
config.global.stubs = {
    'router-link': true,
};

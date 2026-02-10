<script setup lang="ts">
import FormSection from '@/Components/FormSection.vue';
import { Checkbox } from '@/packages/ui/src';
import InputLabel from '@/packages/ui/src/Input/InputLabel.vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { computed, ref, watch } from 'vue';
import { getCurrentOrganizationId } from '@/utils/useUser';
import { useNotificationsStore } from '@/utils/notification';

interface NotificationPreferenceItem {
    type: string;
    label: string;
    category: string;
    email_enabled: boolean;
}

const organizationId = getCurrentOrganizationId();
const queryClient = useQueryClient();
const notificationsStore = useNotificationsStore();

function getXsrfToken(): string | undefined {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : undefined;
}

const { data: preferencesData, isLoading } = useQuery({
    queryKey: ['notification-preferences', organizationId],
    queryFn: async () => {
        const xsrfToken = getXsrfToken();
        const response = await fetch(
            `/api/v1/organizations/${organizationId}/notification-preferences`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                },
                credentials: 'same-origin',
            }
        );
        if (!response.ok) throw new Error(`HTTP error ${response.status}`);
        return response.json() as Promise<{ data: NotificationPreferenceItem[] }>;
    },
    enabled: !!organizationId,
});

const preferences = computed(() => preferencesData.value?.data ?? []);

const criticalPreferences = computed(() =>
    preferences.value.filter((p) => p.category === 'critical')
);

const informationalPreferences = computed(() =>
    preferences.value.filter((p) => p.category === 'informational')
);

const localState = ref<Record<string, boolean>>({});

watch(
    preferences,
    (newPrefs) => {
        for (const pref of newPrefs) {
            if (localState.value[pref.type] === undefined) {
                localState.value[pref.type] = pref.email_enabled;
            }
        }
    },
    { immediate: true }
);

const updateMutation = useMutation({
    mutationFn: async (payload: { notification_type: string; email_enabled: boolean }) => {
        const xsrfToken = getXsrfToken();
        const response = await fetch(
            `/api/v1/organizations/${organizationId}/notification-preferences`,
            {
                method: 'PUT',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            }
        );
        if (!response.ok) throw new Error(`HTTP error ${response.status}`);
        return response.json();
    },
    onSuccess: () => {
        queryClient.invalidateQueries({
            queryKey: ['notification-preferences', organizationId],
        });
    },
    onError: () => {
        notificationsStore.addNotification('error', 'Failed to update notification preference');
    },
});

function togglePreference(type: string, enabled: boolean) {
    localState.value[type] = enabled;
    updateMutation.mutate({
        notification_type: type,
        email_enabled: enabled,
    });
}
</script>

<template>
    <FormSection>
        <template #title>Notification Preferences</template>
        <template #description>
            Manage which notifications you receive via email. In-app notifications are always
            enabled.
        </template>

        <template #form>
            <div class="col-span-6">
                <div v-if="isLoading" class="text-sm text-text-tertiary">
                    Loading preferences...
                </div>

                <div v-else-if="preferences.length === 0" class="text-sm text-text-tertiary">
                    No notification types configured.
                </div>

                <div v-else class="space-y-6">
                    <div v-if="criticalPreferences.length > 0">
                        <h4 class="text-xs font-semibold text-text-tertiary uppercase tracking-wider mb-3">
                            Critical
                        </h4>
                        <div class="space-y-3">
                            <div
                                v-for="pref in criticalPreferences"
                                :key="pref.type"
                                class="flex items-center space-x-2">
                                <Checkbox
                                    :id="'notif-' + pref.type"
                                    :checked="localState[pref.type] ?? pref.email_enabled"
                                    @update:checked="togglePreference(pref.type, $event as boolean)" />
                                <InputLabel
                                    :for="'notif-' + pref.type"
                                    :value="pref.label + ' (email)'" />
                            </div>
                        </div>
                    </div>

                    <div v-if="informationalPreferences.length > 0">
                        <h4 class="text-xs font-semibold text-text-tertiary uppercase tracking-wider mb-3">
                            Informational
                        </h4>
                        <div class="space-y-3">
                            <div
                                v-for="pref in informationalPreferences"
                                :key="pref.type"
                                class="flex items-center space-x-2">
                                <Checkbox
                                    :id="'notif-' + pref.type"
                                    :checked="localState[pref.type] ?? pref.email_enabled"
                                    @update:checked="togglePreference(pref.type, $event as boolean)" />
                                <InputLabel
                                    :for="'notif-' + pref.type"
                                    :value="pref.label + ' (email)'" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </FormSection>
</template>

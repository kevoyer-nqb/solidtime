<script setup lang="ts">
import LoadingSpinner from '@/packages/ui/src/LoadingSpinner.vue';
import NotificationItem from './NotificationItem.vue';
import type { AppNotification } from '@/utils/useNotificationBell';

defineProps<{
    notifications: AppNotification[];
    isLoading: boolean;
}>();

const emit = defineEmits<{
    markAsRead: [notificationId: string];
    markAllAsRead: [];
}>();
</script>

<template>
    <div class="w-96">
        <div class="flex items-center justify-between px-4 py-3 border-b border-default-background-separator">
            <h3 class="text-sm font-semibold text-text-primary">
                Notifications
            </h3>
            <button
                class="text-xs text-accent-primary hover:text-accent-primary/80 transition-colors"
                @click="emit('markAllAsRead')">
                Mark all as read
            </button>
        </div>

        <div class="max-h-[480px] overflow-y-auto">
            <div
                v-if="isLoading"
                class="flex items-center justify-center py-12">
                <LoadingSpinner />
            </div>

            <div
                v-else-if="notifications.length === 0"
                class="flex items-center justify-center py-12">
                <p class="text-sm text-text-tertiary">No notifications</p>
            </div>

            <div v-else class="divide-y divide-default-background-separator">
                <NotificationItem
                    v-for="notification in notifications"
                    :key="notification.id"
                    :notification="notification"
                    @mark-as-read="emit('markAsRead', $event)" />
            </div>
        </div>
    </div>
</template>

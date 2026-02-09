<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { BellIcon } from '@heroicons/vue/20/solid';
import { getCurrentOrganizationId } from '@/utils/useUser';
import type { AppNotification } from '@/types/notification';

const unreadCount = ref(0);
const showDropdown = ref(false);
const notifications = ref<AppNotification[]>([]);
const isLoading = ref(false);
const fetchError = ref(false);
let pollInterval: ReturnType<typeof setInterval> | null = null;
let consecutivePollFailures = 0;

const fetchHeaders: HeadersInit = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

const mutationHeaders: HeadersInit = {
    ...fetchHeaders,
    'Content-Type': 'application/json',
};

async function fetchUnreadCount(): Promise<void> {
    const orgId = getCurrentOrganizationId();
    if (!orgId) return;

    try {
        const response = await fetch(
            `/api/v1/organizations/${orgId}/notifications/unread-count`,
            { headers: fetchHeaders, credentials: 'same-origin' }
        );
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const json = await response.json();
        unreadCount.value = json.data.unread_count;
        consecutivePollFailures = 0;
    } catch (error) {
        consecutivePollFailures++;
        console.warn('[NotificationBell] Failed to fetch unread count:', error);
        if (consecutivePollFailures >= 5 && pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
            console.error(
                '[NotificationBell] Stopped polling after 5 consecutive failures'
            );
        }
    }
}

async function fetchNotifications(): Promise<void> {
    const orgId = getCurrentOrganizationId();
    if (!orgId) return;

    isLoading.value = true;
    fetchError.value = false;
    try {
        const response = await fetch(
            `/api/v1/organizations/${orgId}/notifications?per_page=10`,
            { headers: fetchHeaders, credentials: 'same-origin' }
        );
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const json = await response.json();
        notifications.value = json.data;
    } catch (error) {
        fetchError.value = true;
        console.error('[NotificationBell] Failed to fetch notifications:', error);
    } finally {
        isLoading.value = false;
    }
}

async function markAsRead(notificationId: string): Promise<void> {
    const orgId = getCurrentOrganizationId();
    if (!orgId) return;

    try {
        const response = await fetch(
            `/api/v1/organizations/${orgId}/notifications/${notificationId}/read`,
            {
                method: 'PATCH',
                headers: mutationHeaders,
                credentials: 'same-origin',
            }
        );
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const notification = notifications.value.find(
            (n) => n.id === notificationId
        );
        if (notification) {
            notification.read_at = new Date().toISOString();
        }
        if (unreadCount.value > 0) {
            unreadCount.value--;
        }
    } catch (error) {
        console.error('[NotificationBell] Failed to mark as read:', error);
    }
}

async function markAllAsRead(): Promise<void> {
    const orgId = getCurrentOrganizationId();
    if (!orgId) return;

    try {
        const response = await fetch(
            `/api/v1/organizations/${orgId}/notifications/read-all`,
            {
                method: 'PATCH',
                headers: mutationHeaders,
                credentials: 'same-origin',
            }
        );
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        notifications.value.forEach((n) => {
            n.read_at = n.read_at ?? new Date().toISOString();
        });
        unreadCount.value = 0;
    } catch (error) {
        console.error('[NotificationBell] Failed to mark all as read:', error);
    }
}

function toggleDropdown(): void {
    showDropdown.value = !showDropdown.value;
    if (showDropdown.value) {
        fetchNotifications();
    }
}

function closeDropdown(): void {
    showDropdown.value = false;
}

function formatTime(dateString: string): string {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) {
        return '';
    }
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;

    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `${diffHours}h ago`;

    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 7) return `${diffDays}d ago`;

    return date.toLocaleDateString();
}

function getNotificationTitle(notification: AppNotification): string {
    return notification.data.title || 'Notification';
}

function getNotificationMessage(notification: AppNotification): string {
    return notification.data.message || '';
}

onMounted(() => {
    fetchUnreadCount();
    pollInterval = setInterval(fetchUnreadCount, 60000);
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});
</script>

<template>
    <div class="relative">
        <button
            class="relative flex items-center justify-center w-8 h-8 rounded-full text-icon-default hover:text-icon-active transition-colors"
            @click="toggleDropdown"
            aria-label="Notifications">
            <BellIcon class="w-5 h-5" />
            <span
                v-if="unreadCount > 0"
                class="absolute -top-0.5 -right-0.5 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-accent-300/90 rounded-full">
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <div
            v-if="showDropdown"
            class="fixed inset-0 z-40"
            @click="closeDropdown"></div>

        <div
            v-if="showDropdown"
            class="absolute right-0 top-10 z-50 w-80 max-h-96 overflow-y-auto bg-card-background border border-card-border rounded-lg shadow-lg">
            <div
                class="flex items-center justify-between px-4 py-3 border-b border-card-border">
                <span class="text-sm font-semibold text-text-primary"
                    >Notifications</span
                >
                <button
                    v-if="unreadCount > 0"
                    class="text-xs text-accent-300 hover:text-accent-400"
                    @click="markAllAsRead">
                    Mark all as read
                </button>
            </div>

            <div
                v-if="isLoading"
                class="px-4 py-8 text-center text-text-tertiary text-sm">
                Loading...
            </div>

            <div
                v-else-if="fetchError"
                class="px-4 py-8 text-center text-sm">
                <p class="text-red-500">Failed to load notifications.</p>
                <button
                    class="text-accent-300 hover:text-accent-400 mt-2 text-xs"
                    @click="fetchNotifications">
                    Try again
                </button>
            </div>

            <div
                v-else-if="notifications.length === 0"
                class="px-4 py-8 text-center text-text-tertiary text-sm">
                No notifications yet.
            </div>

            <ul v-else>
                <li
                    v-for="notification in notifications"
                    :key="notification.id"
                    class="px-4 py-3 border-b border-card-border last:border-b-0 cursor-pointer hover:bg-tertiary transition-colors"
                    :class="{
                        'bg-card-background': notification.read_at,
                        'bg-secondary': !notification.read_at,
                    }"
                    @click="
                        !notification.read_at && markAsRead(notification.id)
                    ">
                    <div class="flex items-start gap-2">
                        <div
                            v-if="!notification.read_at"
                            class="mt-1.5 w-2 h-2 rounded-full bg-accent-300 flex-shrink-0"></div>
                        <div
                            v-else
                            class="mt-1.5 w-2 h-2 flex-shrink-0"></div>
                        <div class="flex-1 min-w-0">
                            <p
                                class="text-sm font-medium text-text-primary truncate">
                                {{ getNotificationTitle(notification) }}
                            </p>
                            <p
                                v-if="getNotificationMessage(notification)"
                                class="text-xs text-text-tertiary mt-0.5 line-clamp-2">
                                {{ getNotificationMessage(notification) }}
                            </p>
                            <p class="text-xs text-text-tertiary mt-1">
                                {{ formatTime(notification.created_at) }}
                            </p>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>

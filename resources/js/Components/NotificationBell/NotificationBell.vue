<script setup lang="ts">
import { BellIcon } from '@heroicons/vue/24/outline';
import { Popover, PopoverTrigger, PopoverContent } from '@/packages/ui/src/popover';
import { useNotificationBell } from '@/utils/useNotificationBell';
import NotificationDropdown from './NotificationDropdown.vue';

const {
    displayCount,
    notifications,
    isNotificationsLoading,
    markAsRead,
    markAllAsRead,
    isDropdownOpen,
} = useNotificationBell();

function onOpenChange(open: boolean) {
    isDropdownOpen.value = open;
}
</script>

<template>
    <Popover @update:open="onOpenChange">
        <PopoverTrigger as-child>
            <button
                class="relative inline-flex items-center justify-center rounded-md p-1.5 text-icon-default hover:text-text-primary transition-colors focus:outline-none"
                aria-label="Notifications">
                <BellIcon class="h-5 w-5" />
                <span
                    v-if="displayCount"
                    class="absolute -top-0.5 -right-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white">
                    {{ displayCount }}
                </span>
            </button>
        </PopoverTrigger>
        <PopoverContent
            align="end"
            :side-offset="8"
            class="p-0">
            <NotificationDropdown
                :notifications="notifications"
                :is-loading="isNotificationsLoading"
                @mark-as-read="markAsRead"
                @mark-all-as-read="markAllAsRead" />
        </PopoverContent>
    </Popover>
</template>

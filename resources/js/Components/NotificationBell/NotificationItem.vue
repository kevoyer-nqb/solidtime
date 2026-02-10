<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import type { AppNotification } from '@/utils/useNotificationBell';

dayjs.extend(relativeTime);

const props = defineProps<{
    notification: AppNotification;
}>();

const emit = defineEmits<{
    markAsRead: [notificationId: string];
}>();

const isUnread = computed(() => props.notification.read_at === null);

const relativeTimeText = computed(() => {
    return dayjs(props.notification.created_at).fromNow();
});

const title = computed(() => props.notification.data?.title ?? 'Notification');
const body = computed(() => props.notification.data?.body ?? '');
const actionUrl = computed(() => props.notification.data?.action_url ?? null);

function handleClick() {
    if (isUnread.value) {
        emit('markAsRead', props.notification.id);
    }
    if (actionUrl.value) {
        router.visit(actionUrl.value);
    }
}
</script>

<template>
    <button
        class="w-full text-left px-4 py-3 transition-colors hover:bg-card-background border-l-2"
        :class="
            isUnread
                ? 'border-l-accent-primary/80 bg-accent-primary/5'
                : 'border-l-transparent'
        "
        @click="handleClick">
        <div class="flex items-start justify-between gap-2">
            <p
                class="text-sm leading-snug"
                :class="isUnread ? 'font-semibold text-text-primary' : 'text-text-secondary'">
                {{ title }}
            </p>
            <span
                v-if="isUnread"
                class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-accent-primary"></span>
        </div>
        <p
            v-if="body"
            class="mt-0.5 text-xs text-text-tertiary line-clamp-2">
            {{ body }}
        </p>
        <p class="mt-1 text-xs text-text-tertiary">
            {{ relativeTimeText }}
        </p>
    </button>
</template>

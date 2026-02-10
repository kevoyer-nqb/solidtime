import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { ref, computed } from 'vue';
import { getCurrentOrganizationId } from '@/utils/useUser';

interface NotificationData {
    organization_id: string;
    type: string;
    title: string;
    body: string;
    action_url: string | null;
}

export interface AppNotification {
    id: string;
    type: string;
    data: NotificationData;
    read_at: string | null;
    created_at: string;
    updated_at: string;
}

interface PaginatedNotifications {
    data: AppNotification[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

function getXsrfToken(): string | undefined {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : undefined;
}

async function fetchJson<T>(url: string, options?: RequestInit): Promise<T> {
    const xsrfToken = getXsrfToken();
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
            ...options?.headers,
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`HTTP error ${response.status}`);
    }

    return response.json();
}

export function useNotificationBell() {
    const isDropdownOpen = ref(false);
    const organizationId = getCurrentOrganizationId();

    const {
        data: unreadCountData,
        isLoading: isUnreadCountLoading,
        refetch: refetchUnreadCount,
    } = useQuery({
        queryKey: ['notifications', 'unread-count', organizationId],
        queryFn: () =>
            fetchJson<{ count: number }>(
                `/api/v1/organizations/${organizationId}/notifications/unread-count`
            ),
        refetchInterval: 30_000,
        enabled: !!organizationId,
    });

    const unreadCount = computed(() => unreadCountData.value?.count ?? 0);

    const displayCount = computed(() => {
        const count = unreadCount.value;
        if (count <= 0) return '';
        return count > 9 ? '9+' : String(count);
    });

    const {
        data: notificationsData,
        isLoading: isNotificationsLoading,
        refetch: refetchNotifications,
    } = useQuery({
        queryKey: ['notifications', 'list', organizationId],
        queryFn: () =>
            fetchJson<PaginatedNotifications>(
                `/api/v1/organizations/${organizationId}/notifications`
            ),
        enabled: () => isDropdownOpen.value && !!organizationId,
    });

    const notifications = computed(() => notificationsData.value?.data ?? []);

    const queryClient = useQueryClient();

    const invalidateQueries = () => {
        queryClient.invalidateQueries({
            queryKey: ['notifications', 'unread-count', organizationId],
        });
        queryClient.invalidateQueries({
            queryKey: ['notifications', 'list', organizationId],
        });
    };

    const markAsReadMutation = useMutation({
        mutationFn: (notificationId: string) =>
            fetchJson<{ success: boolean }>(
                `/api/v1/organizations/${organizationId}/notifications/${notificationId}/mark-as-read`,
                { method: 'POST' }
            ),
        onSuccess: () => {
            invalidateQueries();
        },
    });

    const markAllAsReadMutation = useMutation({
        mutationFn: () =>
            fetchJson<{ success: boolean }>(
                `/api/v1/organizations/${organizationId}/notifications/mark-all-as-read`,
                { method: 'POST' }
            ),
        onSuccess: () => {
            invalidateQueries();
        },
    });

    function markAsRead(notificationId: string) {
        markAsReadMutation.mutate(notificationId);
    }

    function markAllAsRead() {
        markAllAsReadMutation.mutate();
    }

    function openDropdown() {
        isDropdownOpen.value = true;
    }

    function closeDropdown() {
        isDropdownOpen.value = false;
    }

    return {
        unreadCount,
        displayCount,
        notifications,
        isNotificationsLoading,
        isUnreadCountLoading,
        markAsRead,
        markAllAsRead,
        isDropdownOpen,
        openDropdown,
        closeDropdown,
        refetchUnreadCount,
        refetchNotifications,
    };
}

export interface NotificationData {
    title: string;
    message: string;
    action_url?: string;
    [key: string]: unknown;
}

export interface AppNotification {
    id: string;
    type: string;
    data: NotificationData;
    read_at: string | null;
    created_at: string;
}

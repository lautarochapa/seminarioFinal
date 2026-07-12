import { useCallback, useState } from 'react';
import { notificationsApi } from '@/api/endpoints';
import { normalizeError, usePaginatedRetailList, useRetailData } from '@/hooks/useRetailList';
import type { Notification } from '@/types/retail';

export function useNotifications() {
  const list = usePaginatedRetailList<Notification, { page?: number }>(
    (filters) => notificationsApi.list(filters),
    { page: 1 },
  );
  const unread = useRetailData(() => notificationsApi.unreadCount().then((res) => res.data.unread_count));
  const [actionError, setActionError] = useState<string | null>(null);

  const markAsRead = useCallback(async (id: number) => {
    setActionError(null);
    try {
      await notificationsApi.markAsRead(id);
      list.refresh();
      unread.refresh();
    } catch (err: unknown) {
      setActionError(normalizeError(err).message);
    }
  }, [list, unread]);

  const markAllAsRead = useCallback(async () => {
    setActionError(null);
    try {
      await notificationsApi.markAllAsRead();
      list.refresh();
      unread.refresh();
    } catch (err: unknown) {
      setActionError(normalizeError(err).message);
    }
  }, [list, unread]);

  return {
    ...list,
    unreadCount: unread.data ?? 0,
    unreadLoading: unread.loading,
    actionError,
    markAsRead,
    markAllAsRead,
  };
}

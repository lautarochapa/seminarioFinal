import { useCallback, useState } from 'react';
import { notificationsApi } from '@/api/endpoints';
import { normalizeError, useRetailData } from '@/hooks/useRetailList';
import type { NotificationPreferences } from '@/types/retail';

export function useNotificationPreferences() {
  const state = useRetailData(() => notificationsApi.preferences().then((res) => res.data));
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const save = useCallback(async (preferences: NotificationPreferences) => {
    setSaving(true);
    setSaveError(null);
    try {
      await notificationsApi.updatePreferences(preferences);
      state.refresh();
    } catch (err: unknown) {
      setSaveError(normalizeError(err).message);
    } finally {
      setSaving(false);
    }
  }, [state]);

  return { ...state, data: state.data ?? [], saving, saveError, save };
}

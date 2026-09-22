import { useCallback, useState } from 'react';
import { useFocusEffect } from 'expo-router';
import { stockApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import type { StockItem } from '@/types/stock';
import type { NormalizedError } from '@/types/api';

export function useStockItem(groupId: number | null, itemId: number) {
  const [state, setState] = useState<{ key: string; data: StockItem | null; loading: boolean; error: NormalizedError | null } | null>(null);
  const [revision, setRevision] = useState(0);
  const key = `${groupId}:${itemId}:${revision}`;
  const refresh = useCallback(() => setRevision((value) => value + 1), []);

  useFocusEffect(useCallback(() => {
    let active = true;
    setState({ key, data: null, loading: Boolean(groupId), error: null });
    if (!groupId) return;

    async function load() {
      try {
        // The existing stock API is paginated and has no single-item GET.
        for (let page = 1; active; page += 1) {
          const response = await stockApi.list(groupId!, { page });
          if (!active) return;
          const item = response.data.find((entry) => entry.id === itemId) ?? null;
          if (item || page >= (response.meta?.last_page ?? 1)) {
            setState({ key, data: item, loading: false, error: null });
            return;
          }
        }
      } catch (error) {
        if (active) setState({ key, data: null, loading: false, error: error instanceof ApiError ? error.normalized : {
          status: 0, code: 'UNKNOWN', message: 'No se pudo cargar el producto.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false,
        } });
      }
    }
    void load();
    return () => { active = false; };
  }, [groupId, itemId, key]));

  const current = state?.key === key ? state : null;
  return { data: current?.data ?? null, loading: current?.loading ?? Boolean(groupId), error: current?.error ?? null, refresh };
}

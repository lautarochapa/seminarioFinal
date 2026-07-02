import { useCallback, useEffect, useState } from 'react';
import { ApiError } from '@/api/client';
import { shoppingListsApi, shoppingListItemsApi } from '@/api/endpoints';
import type { NormalizedError } from '@/types/api';
import type { ShoppingList, ShoppingListItem } from '@/types/shopping';

const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function useShoppingListDetail(groupId: number | null, listId: number | null) {
  const [list, setList] = useState<ShoppingList | null>(null);
  const [items, setItems] = useState<ShoppingListItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);

  const load = useCallback(() => {
    if (!groupId || !listId) { setList(null); setItems([]); return; }
    setLoading(true);
    setError(null);
    Promise.all([shoppingListsApi.get(groupId, listId), shoppingListItemsApi.list(groupId, listId)])
      .then(([listRes, itemsRes]) => {
        setList(listRes.data);
        setItems(Array.isArray(itemsRes.data) ? itemsRes.data : []);
        setLoading(false);
      })
      .catch((err: unknown) => {
        setError(err instanceof ApiError ? err.normalized : FALLBACK);
        setLoading(false);
      });
  }, [groupId, listId]);

  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { load(); }, [load]);

  return { list, items, loading, error, refresh: load };
}

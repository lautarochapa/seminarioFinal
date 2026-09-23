import { useCallback, useRef, useState } from 'react';
import { useFocusEffect } from 'expo-router';
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
  const request = useRef(0);

  const load = useCallback(async () => {
    const current = ++request.current;
    if (!groupId || !listId) { setList(null); setItems([]); setLoading(false); setError(null); return; }
    setLoading(true);
    setError(null);
    try {
      const [listRes, itemsRes] = await Promise.all([shoppingListsApi.get(groupId, listId), shoppingListItemsApi.list(groupId, listId)]);
      if (current !== request.current) return;
      setList(listRes.data);
      setItems(Array.isArray(itemsRes.data) ? itemsRes.data : []);
    } catch (err: unknown) {
      if (current !== request.current) return;
      setError(err instanceof ApiError ? err.normalized : FALLBACK);
    } finally {
      if (current === request.current) setLoading(false);
    }
  }, [groupId, listId]);

  useFocusEffect(useCallback(() => {
    void load();
    return () => { request.current += 1; };
  }, [load]));

  return { list, items, loading, error, refresh: load };
}

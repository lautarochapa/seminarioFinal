import { useState } from 'react';
import { ApiError } from '@/api/client';
import { shoppingListsApi, shoppingSessionsApi, shoppingListItemsApi } from '@/api/endpoints';
import type { NormalizedError } from '@/types/api';
import type { ShoppingSession, ShoppingListItem, ShoppingListItemUpdateRequest } from '@/types/shopping';

const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function useShoppingSession(groupId: number | null) {
  const [session, setSession] = useState<ShoppingSession | null>(null);
  const [starting, setStarting] = useState(false);
  const [finishing, setFinishing] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);

  async function startSession(listId: number): Promise<ShoppingSession | null> {
    if (!groupId) return null;
    setStarting(true);
    setError(null);
    try {
      const res = await shoppingListsApi.startSession(groupId, listId);
      setSession(res.data);
      return res.data;
    } catch (err: unknown) {
      setError(err instanceof ApiError ? err.normalized : FALLBACK);
      return null;
    } finally {
      setStarting(false);
    }
  }

  async function finishSession(sessionId?: number): Promise<ShoppingSession | null> {
    const id = sessionId ?? session?.id;
    if (!groupId || !id) return null;
    setFinishing(true);
    setError(null);
    try {
      const res = await shoppingSessionsApi.finish(groupId, id);
      setSession(res.data);
      return res.data;
    } catch (err: unknown) {
      setError(err instanceof ApiError ? err.normalized : FALLBACK);
      return null;
    } finally {
      setFinishing(false);
    }
  }

  async function updateItem(listId: number, itemId: number, payload: ShoppingListItemUpdateRequest): Promise<ShoppingListItem | null> {
    if (!groupId) return null;
    try {
      const res = await shoppingListItemsApi.update(groupId, listId, itemId, payload);
      return res.data;
    } catch (err: unknown) {
      setError(err instanceof ApiError ? err.normalized : FALLBACK);
      return null;
    }
  }

  return { session, starting, finishing, error, startSession, finishSession, updateItem };
}

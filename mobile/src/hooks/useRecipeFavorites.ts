/* eslint-disable react-hooks/set-state-in-effect */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { ApiError } from '@/api/client';
import { recipeFavoritesApi } from '@/api/endpoints';
import type { NormalizedError } from '@/types/api';
import type { RecipeFavorite } from '@/types/recipe';

const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function useRecipeFavorites() {
  const [data, setData] = useState<RecipeFavorite[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);
  const [savingIds, setSavingIds] = useState<Record<number, boolean>>({});

  const favoriteIds = useMemo(() => new Set(data.map((f) => f.recipe_id)), [data]);

  const refresh = useCallback(() => {
    setLoading(true);
    setError(null);
    recipeFavoritesApi.list()
      .then((res) => setData(res.data))
      .catch((err: unknown) => setError(err instanceof ApiError ? err.normalized : FALLBACK))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { refresh(); }, [refresh]);

  const toggle = useCallback(async (recipeId: number) => {
    if (savingIds[recipeId]) return;
    const wasFavorite = favoriteIds.has(recipeId);
    const previous = data;
    setSavingIds((current) => ({ ...current, [recipeId]: true }));
    if (wasFavorite) {
      setData((current) => current.filter((f) => f.recipe_id !== recipeId));
    }
    try {
      if (wasFavorite) await recipeFavoritesApi.remove(recipeId);
      else await recipeFavoritesApi.add(recipeId);
      await recipeFavoritesApi.list().then((res) => setData(res.data));
    } catch (err: unknown) {
      setData(previous);
      throw err;
    } finally {
      setSavingIds((current) => ({ ...current, [recipeId]: false }));
    }
  }, [data, favoriteIds, savingIds]);

  return { data, favoriteIds, loading, error, savingIds, refresh, toggle };
}
/* eslint-enable react-hooks/set-state-in-effect */


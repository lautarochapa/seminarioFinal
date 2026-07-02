/* eslint-disable react-hooks/set-state-in-effect */
import { useCallback, useEffect, useState } from 'react';
import { ApiError } from '@/api/client';
import { recipesApi } from '@/api/endpoints';
import type { NormalizedError } from '@/types/api';
import type { RecipeDetail } from '@/types/recipe';

const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function useRecipeDetail(recipeId: number) {
  const [data, setData] = useState<RecipeDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);

  const refresh = useCallback(() => {
    if (!recipeId) return;
    setLoading(true);
    setError(null);
    recipesApi.get(recipeId)
      .then((res) => setData(res.data))
      .catch((err: unknown) => setError(err instanceof ApiError ? err.normalized : FALLBACK))
      .finally(() => setLoading(false));
  }, [recipeId]);

  useEffect(() => { refresh(); }, [refresh]);

  return { data, loading, error, refresh };
}
/* eslint-enable react-hooks/set-state-in-effect */


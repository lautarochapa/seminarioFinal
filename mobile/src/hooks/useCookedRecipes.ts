/* eslint-disable react-hooks/set-state-in-effect */
import { useCallback, useEffect, useState } from 'react';
import { ApiError } from '@/api/client';
import { recipesApi } from '@/api/endpoints';
import type { NormalizedError } from '@/types/api';
import type { CookedRecipeLog } from '@/types/recipe';

const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function useCookedRecipes() {
  const [data, setData] = useState<CookedRecipeLog[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);

  const refresh = useCallback(() => {
    setLoading(true);
    setError(null);
    recipesApi.cookedHistory()
      .then((res) => setData(Array.isArray(res.data) ? res.data : []))
      .catch((err: unknown) => setError(err instanceof ApiError ? err.normalized : FALLBACK))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { refresh(); }, [refresh]);

  return { data, loading, error, refresh };
}

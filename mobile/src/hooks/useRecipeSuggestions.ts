/* eslint-disable react-hooks/set-state-in-effect */
import { useCallback, useEffect, useState } from 'react';
import { ApiError } from '@/api/client';
import { recipeSuggestionsApi } from '@/api/endpoints';
import type { NormalizedError } from '@/types/api';
import type { RecipeSuggestion } from '@/types/recipe';

const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function useRecipeSuggestions(groupId: number | null) {
  const [data, setData] = useState<RecipeSuggestion[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);

  const refresh = useCallback(() => {
    setLoading(true);
    setError(null);
    recipeSuggestionsApi.list(groupId)
      .then((res) => setData(res.data))
      .catch((err: unknown) => setError(err instanceof ApiError ? err.normalized : FALLBACK))
      .finally(() => setLoading(false));
  }, [groupId]);

  useEffect(() => { refresh(); }, [refresh]);

  return { data, loading, error, refresh };
}
/* eslint-enable react-hooks/set-state-in-effect */


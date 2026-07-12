/* eslint-disable react-hooks/set-state-in-effect */
import { useCallback, useEffect, useState } from 'react';
import { ApiError } from '@/api/client';
import { recipeSuggestionsApi } from '@/api/endpoints';
import type { NormalizedError } from '@/types/api';
import type { RecipeSuggestion } from '@/types/recipe';

const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function useRecipeSuggestions(groupId: number | null) {
  const [data, setData] = useState<RecipeSuggestion[]>([]);
  const [invalidCount, setInvalidCount] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);

  const refresh = useCallback(() => {
    setLoading(true);
    setError(null);
    setInvalidCount(0);
    recipeSuggestionsApi.listWithDiagnostics(groupId)
      .then((result) => {
        setData(result.response.data);
        setInvalidCount(result.invalidCount);
      })
      .catch((err: unknown) => {
        setData([]);
        setError(err instanceof ApiError ? err.normalized : FALLBACK);
      })
      .finally(() => setLoading(false));
  }, [groupId]);

  useEffect(() => { refresh(); }, [refresh]);

  return { data, invalidCount, loading, error, refresh };
}
/* eslint-enable react-hooks/set-state-in-effect */


/* eslint-disable react-hooks/set-state-in-effect */
import { useCallback, useEffect, useState } from 'react';
import { ApiError } from '@/api/client';
import { mealPlansApi } from '@/api/endpoints';
import type { NormalizedError } from '@/types/api';
import type { MealPlan } from '@/types/mealPlan';

const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function useMealPlans(groupId: number | null) {
  const [data, setData] = useState<MealPlan[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);

  const refresh = useCallback(() => {
    if (!groupId) { setData([]); return; }
    setLoading(true);
    setError(null);
    mealPlansApi.list(groupId)
      .then((res) => setData(res.data))
      .catch((err: unknown) => setError(err instanceof ApiError ? err.normalized : FALLBACK))
      .finally(() => setLoading(false));
  }, [groupId]);

  useEffect(() => { refresh(); }, [refresh]);

  return { data, loading, error, refresh };
}
/* eslint-enable react-hooks/set-state-in-effect */


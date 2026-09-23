import { useCallback, useRef, useState } from 'react';
import { useFocusEffect } from 'expo-router';
import { ApiError } from '@/api/client';
import { mealPlansApi } from '@/api/endpoints';
import type { NormalizedError } from '@/types/api';
import type { MealPlan } from '@/types/mealPlan';

const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function useMealPlanDetail(groupId: number | null, planId: number) {
  const [data, setData] = useState<MealPlan | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);
  const request = useRef(0);

  const refresh = useCallback(() => {
    const current = ++request.current;
    if (!groupId || !planId) { setData(null); setLoading(false); return; }
    setLoading(true);
    setError(null);
    mealPlansApi.get(groupId, planId)
      .then((res) => { if (current === request.current) setData(res.data); })
      .catch((err: unknown) => { if (current === request.current) setError(err instanceof ApiError ? err.normalized : FALLBACK); })
      .finally(() => { if (current === request.current) setLoading(false); });
  }, [groupId, planId]);

  useFocusEffect(useCallback(() => { setData(null); refresh(); return () => { request.current++; }; }, [refresh]));

  return { data, loading, error, refresh };
}


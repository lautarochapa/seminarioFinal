import { useCallback, useRef, useState } from 'react';
import { useFocusEffect } from 'expo-router';
import { ApiError } from '@/api/client';
import { mealPlansApi } from '@/api/endpoints';
import type { NormalizedError } from '@/types/api';
import type { MealPlan } from '@/types/mealPlan';

const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function useMealPlans(groupId: number | null, dateFrom?: string, dateTo?: string) {
  const [data, setData] = useState<MealPlan[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);
  const request = useRef(0);
  const refresh = useCallback(async () => {
    const current = ++request.current;
    setData([]);
    setError(null);
    setLoading(Boolean(groupId));
    if (!groupId) return;
    try {
      const plans: MealPlan[] = [];
      let page = 1;
      let lastPage = 1;
      do {
        const res = await mealPlansApi.list(groupId, { page, per_page: 100, date_from: dateFrom, date_to: dateTo });
        if (current !== request.current) return;
        plans.push(...res.data);
        lastPage = res.meta?.last_page ?? 1;
        page++;
      } while (page <= lastPage);
      setData(plans);
    } catch (err) {
      if (current === request.current) setError(err instanceof ApiError ? err.normalized : FALLBACK);
    } finally {
      if (current === request.current) setLoading(false);
    }
  }, [groupId, dateFrom, dateTo]);
  useFocusEffect(useCallback(() => {
    void refresh();
    return () => { request.current++; };
  }, [refresh]));
  return { data, loading, error, refresh };
}

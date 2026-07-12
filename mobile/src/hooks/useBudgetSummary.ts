import { useCallback, useEffect, useState } from 'react';
import { ApiError } from '@/api/client';
import { budgetsApi } from '@/api/endpoints';
import type { NormalizedError } from '@/types/api';
import type { BudgetSummary } from '@/types/budget';

const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function useBudgetSummary(groupId: number | null, budgetId: number | null) {
  const [data, setData] = useState<BudgetSummary | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);

  const load = useCallback(() => {
    if (!groupId || !budgetId) { setData(null); return; }
    setLoading(true);
    setError(null);
    budgetsApi.summary(groupId, budgetId)
      .then((res) => { setData(res.data); setLoading(false); })
      .catch((err: unknown) => { setError(err instanceof ApiError ? err.normalized : FALLBACK); setLoading(false); });
  }, [groupId, budgetId]);

  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { load(); }, [load]);

  return { data, loading, error, refresh: load };
}

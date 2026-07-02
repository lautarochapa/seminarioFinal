import { useCallback, useEffect, useState } from 'react';
import { ApiError } from '@/api/client';
import { purchasesApi } from '@/api/endpoints';
import type { NormalizedError } from '@/types/api';
import type { Purchase } from '@/types/purchase';

const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function usePurchaseDetail(groupId: number | null, purchaseId: number | null) {
  const [data, setData] = useState<Purchase | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);

  const load = useCallback(() => {
    if (!groupId || !purchaseId) { setData(null); return; }
    setLoading(true);
    setError(null);
    purchasesApi.get(groupId, purchaseId)
      .then((res) => { setData(res.data); setLoading(false); })
      .catch((err: unknown) => { setError(err instanceof ApiError ? err.normalized : FALLBACK); setLoading(false); });
  }, [groupId, purchaseId]);

  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { load(); }, [load]);

  return { data, loading, error, refresh: load };
}

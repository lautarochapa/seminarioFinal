import { useCallback, useEffect, useState } from 'react';
import { stockLocationsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import type { StockLocation } from '@/types/stock';
import type { NormalizedError } from '@/types/api';

interface StockLocationsState {
  data: StockLocation[];
  loading: boolean;
  error: NormalizedError | null;
  refresh: () => void;
}

export function useStockLocations(groupId: number | null): StockLocationsState {
  const [data, setData] = useState<StockLocation[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);
  const [version, setVersion] = useState(0);

  const refresh = useCallback(() => setVersion((v) => v + 1), []);

  useEffect(() => {
    /* eslint-disable react-hooks/set-state-in-effect */
    if (groupId === null) {
      setData([]);
      setLoading(false);
      return;
    }

    let cancelled = false;
    setLoading(true);
    setError(null);
    /* eslint-enable react-hooks/set-state-in-effect */

    stockLocationsApi.list(groupId).then((res) => {
      if (!cancelled) {
        setData(Array.isArray(res.data) ? res.data : []);
        setLoading(false);
      }
    }).catch((err: unknown) => {
      if (!cancelled) {
        if (err instanceof ApiError) {
          setError(err.normalized);
        } else {
          setError({ status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false });
        }
        setLoading(false);
      }
    });

    return () => { cancelled = true; };
  }, [groupId, version]);

  return { data, loading, error, refresh };
}

import { useEffect, useState } from 'react';
import { ApiError } from '@/api/client';
import { unitsApi } from '@/api/endpoints';
import type { NormalizedError } from '@/types/api';
import type { Unit } from '@/types/unit';

const FALLBACK_ERROR: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function useUnits() {
  const [data, setData] = useState<Unit[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);

  useEffect(() => {
    /* eslint-disable react-hooks/set-state-in-effect */
    let cancelled = false;
    setLoading(true);
    setError(null);
    unitsApi.list()
      .then((res) => { if (!cancelled) { setData(res.data); setLoading(false); } })
      .catch((err: unknown) => {
        if (!cancelled) {
          setError(err instanceof ApiError ? err.normalized : FALLBACK_ERROR);
          setLoading(false);
        }
      });
    return () => { cancelled = true; };
    /* eslint-enable react-hooks/set-state-in-effect */
  }, []);

  return { data, loading, error };
}

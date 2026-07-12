import { useCallback, useEffect, useState } from 'react';
import { profileApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import type { Profile } from '@/types/profile';
import type { NormalizedError } from '@/types/api';

interface ProfileState {
  data: Profile | null;
  loading: boolean;
  error: NormalizedError | null;
  refresh: () => void;
}

export function useProfile(): ProfileState {
  const [data, setData] = useState<Profile | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<NormalizedError | null>(null);
  const [version, setVersion] = useState(0);

  const refresh = useCallback(() => setVersion((v) => v + 1), []);

  useEffect(() => {
    let cancelled = false;
    /* eslint-disable react-hooks/set-state-in-effect */
    setLoading(true);
    setError(null);
    /* eslint-enable react-hooks/set-state-in-effect */

    profileApi.get().then((res) => {
      if (!cancelled) {
        setData(res.data);
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
  }, [version]);

  return { data, loading, error, refresh };
}

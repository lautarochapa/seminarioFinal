import { useCallback, useEffect, useState } from 'react';
import { familyGroupsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import type { FamilyGroup } from '@/types/familyGroup';
import type { NormalizedError } from '@/types/api';

interface FamilyGroupsState {
  data: FamilyGroup[];
  loading: boolean;
  error: NormalizedError | null;
  refresh: () => void;
}

interface UseFamilyGroupsOptions {
  onLoaded?: (groups: FamilyGroup[]) => void;
}

export function useFamilyGroups(options?: UseFamilyGroupsOptions): FamilyGroupsState {
  const [data, setData] = useState<FamilyGroup[]>([]);
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

    familyGroupsApi.list().then((res) => {
      if (!cancelled) {
        setData(res.data);
        setLoading(false);
        options?.onLoaded?.(res.data);
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
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [version]);

  return { data, loading, error, refresh };
}

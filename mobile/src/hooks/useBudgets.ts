/* eslint-disable react-hooks/set-state-in-effect */
import { useCallback, useEffect, useRef, useState } from 'react';
import { ApiError } from '@/api/client';
import { budgetsApi } from '@/api/endpoints';
import type { NormalizedError, PaginatedMeta } from '@/types/api';
import type { Budget } from '@/types/budget';

interface State {
  data: Budget[];
  meta: PaginatedMeta;
  loading: boolean;
  error: NormalizedError | null;
}

const DEFAULT_META: PaginatedMeta = { current_page: 1, per_page: 20, total: 0, last_page: 1 };
const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function useBudgets(groupId: number | null) {
  const [state, setState] = useState<State>({ data: [], meta: DEFAULT_META, loading: false, error: null });
  const version = useRef(0);

  const fetch = useCallback((gid: number) => {
    const v = ++version.current;
    setState((s) => ({ ...s, loading: true, error: null }));
    budgetsApi.list(gid, { per_page: 20 })
      .then((res) => {
        if (version.current !== v) return;
        setState((s) => ({ ...s, data: res.data, meta: res.meta, loading: false }));
      })
      .catch((err: unknown) => {
        if (version.current !== v) return;
        setState((s) => ({ ...s, loading: false, error: err instanceof ApiError ? err.normalized : FALLBACK }));
      });
  }, []);

  useEffect(() => {
    if (!groupId) { setState({ data: [], meta: DEFAULT_META, loading: false, error: null }); return; }
    fetch(groupId);
  }, [groupId, fetch]);

  const refresh = useCallback(() => { if (groupId) fetch(groupId); }, [groupId, fetch]);

  return { ...state, refresh };
}
/* eslint-enable react-hooks/set-state-in-effect */

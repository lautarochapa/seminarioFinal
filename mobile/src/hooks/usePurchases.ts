/* eslint-disable react-hooks/set-state-in-effect */
import { useCallback, useEffect, useRef, useState } from 'react';
import { ApiError } from '@/api/client';
import { purchasesApi } from '@/api/endpoints';
import type { NormalizedError, PaginatedMeta } from '@/types/api';
import type { Purchase, PurchaseFilters } from '@/types/purchase';

interface State {
  data: Purchase[];
  meta: PaginatedMeta;
  loading: boolean;
  loadingMore: boolean;
  error: NormalizedError | null;
}

const DEFAULT_META: PaginatedMeta = { current_page: 1, per_page: 20, total: 0, last_page: 1 };
const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function usePurchases(groupId: number | null) {
  const [state, setState] = useState<State>({ data: [], meta: DEFAULT_META, loading: false, loadingMore: false, error: null });
  const [filters, setFiltersRaw] = useState<PurchaseFilters>({});
  const pageRef = useRef(1);
  const version = useRef(0);

  const fetch = useCallback((gid: number, f: PurchaseFilters, page: number, append: boolean) => {
    const v = ++version.current;
    setState((s) => ({ ...s, loading: !append, loadingMore: append, error: null }));
    purchasesApi.list(gid, { ...f, page, per_page: 20 })
      .then((res) => {
        if (version.current !== v) return;
        setState((s) => ({ ...s, data: append ? [...s.data, ...res.data] : res.data, meta: res.meta, loading: false, loadingMore: false }));
      })
      .catch((err: unknown) => {
        if (version.current !== v) return;
        setState((s) => ({ ...s, loading: false, loadingMore: false, error: err instanceof ApiError ? err.normalized : FALLBACK }));
      });
  }, []);

  useEffect(() => {
    if (!groupId) { setState({ data: [], meta: DEFAULT_META, loading: false, loadingMore: false, error: null }); return; }
    pageRef.current = 1;
    fetch(groupId, filters, 1, false);
  }, [groupId, filters, fetch]);

  const setFilters = useCallback((f: PurchaseFilters) => { pageRef.current = 1; setFiltersRaw(f); }, []);

  const loadMore = useCallback(() => {
    if (!groupId || state.loading || state.loadingMore || pageRef.current >= state.meta.last_page) return;
    pageRef.current += 1;
    fetch(groupId, filters, pageRef.current, true);
  }, [groupId, state.loading, state.loadingMore, state.meta.last_page, filters, fetch]);

  const refresh = useCallback(() => {
    if (!groupId) return;
    pageRef.current = 1;
    fetch(groupId, filters, 1, false);
  }, [groupId, filters, fetch]);

  return { ...state, setFilters, loadMore, refresh };
}
/* eslint-enable react-hooks/set-state-in-effect */

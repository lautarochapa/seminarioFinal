import { useCallback, useEffect, useState } from 'react';
import { ApiError } from '@/api/client';
import type { NormalizedError, PaginatedMeta } from '@/types/api';

function unknownError(): NormalizedError {
  return {
    status: 0,
    code: 'UNKNOWN',
    message: 'No se pudo completar la operacion.',
    fieldErrors: {},
    traceId: '',
    isNetworkError: false,
    isTimeoutError: false,
  };
}

export function normalizeError(error: unknown): NormalizedError {
  return error instanceof ApiError ? error.normalized : unknownError();
}

export function useRetailData<T>(loader: () => Promise<T>, deps: React.DependencyList = []) {
  const [data, setData] = useState<T | null>(null);
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
    loader()
      .then((result) => {
        if (!cancelled) setData(result);
      })
      .catch((err: unknown) => {
        if (!cancelled) setError(normalizeError(err));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [version, ...deps]);

  return { data, loading, error, refresh };
}

export function usePaginatedRetailList<T, F extends { page?: number }>(
  loader: (filters: F) => Promise<{ data: T[]; meta: PaginatedMeta }>,
  initialFilters: F,
) {
  const [data, setData] = useState<T[]>([]);
  const [meta, setMeta] = useState<PaginatedMeta | null>(null);
  const [filters, setFiltersState] = useState<F>(initialFilters);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);
  const [version, setVersion] = useState(0);

  const refresh = useCallback(() => {
    setFiltersState((current) => ({ ...current, page: 1 }));
    setVersion((v) => v + 1);
  }, []);

  const setFilters = useCallback((next: F) => {
    setFiltersState({ ...next, page: 1 });
    setVersion((v) => v + 1);
  }, []);

  const loadMore = useCallback(() => {
    if (!meta || meta.current_page >= meta.last_page || loading || loadingMore) return;
    setFiltersState((current) => ({ ...current, page: meta.current_page + 1 }));
    setVersion((v) => v + 1);
  }, [loading, loadingMore, meta]);

  useEffect(() => {
    let cancelled = false;
    const page = filters.page ?? 1;
    /* eslint-disable react-hooks/set-state-in-effect */
    if (page === 1) {
      setLoading(true);
      setError(null);
    } else {
      setLoadingMore(true);
    }
    /* eslint-enable react-hooks/set-state-in-effect */
    loader(filters)
      .then((result) => {
        if (cancelled) return;
        setData((previous) => (page === 1 ? result.data : [...previous, ...result.data]));
        setMeta(result.meta);
      })
      .catch((err: unknown) => {
        if (!cancelled) setError(normalizeError(err));
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
          setLoadingMore(false);
        }
      });
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [version]);

  return { data, meta, filters, loading, loadingMore, error, refresh, loadMore, setFilters };
}

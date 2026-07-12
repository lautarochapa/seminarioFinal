/* eslint-disable react-hooks/set-state-in-effect */
import { useCallback, useEffect, useRef, useState } from 'react';
import { ApiError } from '@/api/client';
import { recipesApi } from '@/api/endpoints';
import type { NormalizedError, PaginatedMeta } from '@/types/api';
import type { RecipeFilters, RecipeSummary } from '@/types/recipe';

const DEFAULT_META: PaginatedMeta = { current_page: 1, per_page: 20, total: 0, last_page: 1 };
const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function useRecipes() {
  const [data, setData] = useState<RecipeSummary[]>([]);
  const [meta, setMeta] = useState(DEFAULT_META);
  const [loading, setLoading] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);
  const [filters, setFiltersRaw] = useState<RecipeFilters>({});
  const pageRef = useRef(1);
  const version = useRef(0);

  const fetch = useCallback((f: RecipeFilters, page: number, append: boolean) => {
    const v = ++version.current;
    setLoading(!append);
    setLoadingMore(append);
    setError(null);
    const request = f.search ? recipesApi.search : recipesApi.list;
    request({ ...f, page, per_page: 20 })
      .then((res) => {
        if (version.current !== v) return;
        setData((current) => (append ? [...current, ...res.data] : res.data));
        setMeta(res.meta);
      })
      .catch((err: unknown) => setError(err instanceof ApiError ? err.normalized : FALLBACK))
      .finally(() => {
        if (version.current === v) {
          setLoading(false);
          setLoadingMore(false);
        }
      });
  }, []);

  useEffect(() => {
    pageRef.current = 1;
    fetch(filters, 1, false);
  }, [filters, fetch]);

  const setFilters = useCallback((f: RecipeFilters) => {
    pageRef.current = 1;
    setFiltersRaw(f);
  }, []);

  const loadMore = useCallback(() => {
    if (loading || loadingMore || pageRef.current >= meta.last_page) return;
    pageRef.current += 1;
    fetch(filters, pageRef.current, true);
  }, [loading, loadingMore, meta.last_page, filters, fetch]);

  const refresh = useCallback(() => {
    pageRef.current = 1;
    fetch(filters, 1, false);
  }, [filters, fetch]);

  return { data, meta, loading, loadingMore, error, filters, setFilters, loadMore, refresh };
}
/* eslint-enable react-hooks/set-state-in-effect */

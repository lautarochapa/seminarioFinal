import { useCallback, useEffect, useRef, useState } from 'react';
import { productsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import type { ProductSummary, ProductFilters } from '@/types/product';
import type { NormalizedError, PaginatedMeta } from '@/types/api';

interface ProductsState {
  data: ProductSummary[];
  meta: PaginatedMeta | null;
  loading: boolean;
  loadingMore: boolean;
  error: NormalizedError | null;
  refresh: () => void;
  loadMore: () => void;
  setFilters: (f: ProductFilters) => void;
}

export function useProducts(initialFilters?: ProductFilters): ProductsState {
  const [data, setData] = useState<ProductSummary[]>([]);
  const [meta, setMeta] = useState<PaginatedMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);
  const [filters, setFiltersState] = useState<ProductFilters>(initialFilters ?? {});
  const [version, setVersion] = useState(0);

  const refresh = useCallback(() => {
    setFiltersState((f) => ({ ...f, page: 1 }));
    setVersion((v) => v + 1);
  }, []);

  const setFilters = useCallback((f: ProductFilters) => {
    setFiltersState({ ...f, page: 1 });
    setVersion((v) => v + 1);
  }, []);

  const loadMore = useCallback(() => {
    if (!meta || meta.current_page >= meta.last_page || loadingMore || loading) return;
    setFiltersState((f) => ({ ...f, page: (meta.current_page ?? 1) + 1 }));
    setVersion((v) => v + 1);
  }, [meta, loadingMore, loading]);

  const isFirstPageRef = useRef(true);

  useEffect(() => {
    let cancelled = false;
    const page = filters.page ?? 1;
    isFirstPageRef.current = page === 1;

    /* eslint-disable react-hooks/set-state-in-effect */
    if (page === 1) {
      setLoading(true);
      setError(null);
    } else {
      setLoadingMore(true);
    }
    /* eslint-enable react-hooks/set-state-in-effect */

    productsApi.list(filters).then((res) => {
      if (!cancelled) {
        if (page === 1) {
          setData(res.data);
        } else {
          setData((prev) => [...prev, ...res.data]);
        }
        setMeta(res.meta);
        setLoading(false);
        setLoadingMore(false);
      }
    }).catch((err: unknown) => {
      if (!cancelled) {
        if (err instanceof ApiError) {
          setError(err.normalized);
        } else {
          setError({ status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false });
        }
        setLoading(false);
        setLoadingMore(false);
      }
    });

    return () => { cancelled = true; };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [version]);

  return { data, meta, loading, loadingMore, error, refresh, loadMore, setFilters };
}

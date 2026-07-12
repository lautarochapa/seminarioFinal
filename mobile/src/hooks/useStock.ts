import { useCallback, useEffect, useState } from 'react';
import { stockApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import type { StockItem, StockFilters } from '@/types/stock';
import type { NormalizedError, PaginatedMeta } from '@/types/api';

interface StockState {
  data: StockItem[];
  meta: PaginatedMeta | null;
  loading: boolean;
  loadingMore: boolean;
  error: NormalizedError | null;
  refresh: () => void;
  loadMore: () => void;
}

export function useStock(groupId: number | null, filters?: StockFilters): StockState {
  const [data, setData] = useState<StockItem[]>([]);
  const [meta, setMeta] = useState<PaginatedMeta | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);
  const [page, setPage] = useState(1);
  const [version, setVersion] = useState(0);

  const refresh = useCallback(() => {
    setPage(1);
    setVersion((v) => v + 1);
  }, []);

  const loadMore = useCallback(() => {
    if (!meta || meta.current_page >= meta.last_page || loadingMore || loading) return;
    setPage((p) => p + 1);
    setVersion((v) => v + 1);
  }, [meta, loadingMore, loading]);

  useEffect(() => {
    /* eslint-disable react-hooks/set-state-in-effect */
    if (groupId === null) {
      setData([]);
      setMeta(null);
      setLoading(false);
      return;
    }

    let cancelled = false;
    if (page === 1) {
      setLoading(true);
      setError(null);
    } else {
      setLoadingMore(true);
    }
    /* eslint-enable react-hooks/set-state-in-effect */

    stockApi.list(groupId, { ...filters, page }).then((res) => {
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
  }, [groupId, page, version]);

  return { data, meta, loading, loadingMore, error, refresh, loadMore };
}

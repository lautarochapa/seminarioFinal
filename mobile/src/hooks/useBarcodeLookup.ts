import { useCallback, useState } from 'react';
import { productsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import type { ProductDetail } from '@/types/product';
import type { NormalizedError } from '@/types/api';

interface BarcodeLookupState {
  result: ProductDetail | null;
  loading: boolean;
  error: NormalizedError | null;
  notFound: boolean;
  lookup: (barcode: string) => Promise<ProductDetail | null>;
  reset: () => void;
}

export function useBarcodeLookup(): BarcodeLookupState {
  const [result, setResult] = useState<ProductDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);
  const [notFound, setNotFound] = useState(false);

  const reset = useCallback(() => {
    setResult(null);
    setError(null);
    setNotFound(false);
  }, []);

  const lookup = useCallback(async (barcode: string): Promise<ProductDetail | null> => {
    const trimmed = barcode.trim();
    if (!trimmed) return null;

    setLoading(true);
    setError(null);
    setNotFound(false);
    setResult(null);

    try {
      const res = await productsApi.findByBarcode(trimmed);
      setResult(res.data);
      return res.data;
    } catch (err: unknown) {
      if (err instanceof ApiError) {
        if (err.normalized.status === 404) {
          setNotFound(true);
        } else {
          setError(err.normalized);
        }
      } else {
        setError({ status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false });
      }
      return null;
    } finally {
      setLoading(false);
    }
  }, []);

  return { result, loading, error, notFound, lookup, reset };
}

import { pricesApi } from '@/api/endpoints';
import { useRetailData } from '@/hooks/useRetailList';

export function usePriceComparison(productId: number | null) {
  return useRetailData(() => {
    if (!productId) {
      return Promise.resolve({ product_id: 0, prices: [], best: null, partial_errors: [] });
    }
    return pricesApi.compare(productId);
  }, [productId]);
}

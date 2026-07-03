import { pricesApi } from '@/api/endpoints';
import { usePaginatedRetailList } from '@/hooks/useRetailList';
import type { PriceHistoryFilters } from '@/types/retail';

export function usePriceHistory(productId: number | null) {
  return usePaginatedRetailList(
    (filters: PriceHistoryFilters) => {
      if (!productId) return Promise.resolve({ data: [], meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 } });
      return pricesApi.history(productId, filters);
    },
    {},
  );
}

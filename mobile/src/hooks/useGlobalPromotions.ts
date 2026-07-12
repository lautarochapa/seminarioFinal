import { promotionsApi } from '@/api/endpoints';
import { usePaginatedRetailList } from '@/hooks/useRetailList';
import type { PromotionFilters } from '@/types/retail';

export function useGlobalPromotions(initialFilters: PromotionFilters = {}) {
  return usePaginatedRetailList(
    (filters: PromotionFilters) => promotionsApi.global(filters),
    initialFilters,
  );
}

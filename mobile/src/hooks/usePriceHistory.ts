import { pricesApi } from '@/api/endpoints';
import { useRetailData } from '@/hooks/useRetailList';

export function usePriceHistory(supermarketProductId: number | null) {
  const state = useRetailData(() => {
    if (!supermarketProductId) return Promise.resolve([]);
    return pricesApi.history(supermarketProductId).then((res) => res.data);
  }, [supermarketProductId]);
  return { ...state, data: state.data ?? [] };
}

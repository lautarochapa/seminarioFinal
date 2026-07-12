import { promotionsApi } from '@/api/endpoints';
import { useRetailData } from '@/hooks/useRetailList';

export function usePromotions(branchId: number | null) {
  const state = useRetailData(() => {
    if (!branchId) return Promise.resolve([]);
    return promotionsApi.byBranch(branchId).then((res) => res.data);
  }, [branchId]);
  return { ...state, data: state.data ?? [] };
}

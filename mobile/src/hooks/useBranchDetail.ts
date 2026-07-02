import { branchesApi } from '@/api/endpoints';
import { useRetailData } from '@/hooks/useRetailList';

export function useBranchDetail(branchId: number | null) {
  return useRetailData(() => {
    if (!branchId) return Promise.resolve(null);
    return branchesApi.get(branchId).then((res) => res.data);
  }, [branchId]);
}

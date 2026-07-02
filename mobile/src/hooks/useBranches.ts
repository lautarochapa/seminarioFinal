import { branchesApi } from '@/api/endpoints';
import { useRetailData } from '@/hooks/useRetailList';
import type { BranchFilters } from '@/types/retail';

export function useBranches(filters?: BranchFilters) {
  const state = useRetailData(() => branchesApi.list(filters).then((res) => res.data), [JSON.stringify(filters ?? {})]);
  return { ...state, data: state.data ?? [] };
}

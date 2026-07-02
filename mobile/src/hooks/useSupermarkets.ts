import { supermarketsApi } from '@/api/endpoints';
import { useRetailData } from '@/hooks/useRetailList';

export function useSupermarkets() {
  const state = useRetailData(() => supermarketsApi.list().then((res) => res.data));
  return { ...state, data: state.data ?? [] };
}

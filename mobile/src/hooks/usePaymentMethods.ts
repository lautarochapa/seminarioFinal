import { paymentMethodsApi } from '@/api/endpoints';
import { useRetailData } from '@/hooks/useRetailList';

export function usePaymentMethods() {
  const state = useRetailData(() => paymentMethodsApi.list().then((res) => res.data));
  return { ...state, data: state.data ?? [] };
}

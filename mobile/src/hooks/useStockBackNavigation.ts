import { useCallback } from 'react';
import { BackHandler, Platform } from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';

export function useStockBackNavigation(itemId?: number) {
  const router = useRouter();
  // These routes are hidden tabs, so generic back() can select Inicio instead of stock.
  const goBack = useCallback(() => {
    if (itemId !== undefined) {
      router.replace({ pathname: '/(app)/stock/[id]' as never, params: { id: String(itemId) } });
    } else {
      router.replace('/(app)/stock' as never);
    }
  }, [router, itemId]);
  useFocusEffect(useCallback(() => {
    if (Platform.OS !== 'android') return;
    const subscription = BackHandler.addEventListener('hardwareBackPress', () => {
      goBack();
      return true;
    });
    return () => subscription.remove();
  }, [goBack]));
  return goBack;
}

import { useCallback } from 'react';
import { BackHandler, Platform } from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';

// Detail screens are hidden tabs; history.back() can select Inicio unexpectedly.
export function useSectionBackNavigation(pathname: string, params: Record<string, string> = {}) {
  const router = useRouter();
  const serialized = JSON.stringify(params);
  const goBack = useCallback(() => {
    router.replace({ pathname: pathname as never, params: JSON.parse(serialized) });
  }, [router, pathname, serialized]);
  useFocusEffect(useCallback(() => {
    if (Platform.OS !== 'android') return;
    const subscription = BackHandler.addEventListener('hardwareBackPress', () => { goBack(); return true; });
    return () => subscription.remove();
  }, [goBack]));
  return goBack;
}

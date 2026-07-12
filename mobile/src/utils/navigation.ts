import { router } from 'expo-router';

export function goBackOrHome(): void {
  if (router.canGoBack()) {
    router.back();
  } else {
    router.replace('/(app)' as never);
  }
}

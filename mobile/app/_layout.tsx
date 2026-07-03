import { Slot, useRouter, useSegments } from 'expo-router';
import { useEffect } from 'react';
import { View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AuthProvider, useAuth } from '@/auth/AuthContext';
import { FamilyGroupProvider, useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { ErrorBoundary } from '@/components/ErrorBoundary';
import { LoadingScreen } from '@/components/LoadingScreen';
import { OfflineBanner } from '@/components/OfflineBanner';

function NavigationGuard() {
  const { state } = useAuth();
  const { clearGroup } = useFamilyGroupContext();
  const router = useRouter();
  const segments = useSegments();

  useEffect(() => {
    if (state === 'initializing') return;

    const inAuthGroup = segments[0] === '(auth)';

    if (state === 'unauthenticated' && !inAuthGroup) {
      clearGroup();
      router.replace('/(auth)/login' as never);
    } else if (state === 'authenticated' && inAuthGroup) {
      router.replace('/(app)' as never);
    }
  }, [state, segments, router, clearGroup]);

  if (state === 'initializing') {
    return <LoadingScreen message="CocinaComidaControl" />;
  }

  return (
    <View style={{ flex: 1 }}>
      <OfflineBanner />
      <ErrorBoundary onGoHome={() => router.replace('/(app)' as never)}>
        <Slot />
      </ErrorBoundary>
    </View>
  );
}

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <AuthProvider>
        <FamilyGroupProvider>
          <NavigationGuard />
        </FamilyGroupProvider>
      </AuthProvider>
    </SafeAreaProvider>
  );
}

import { Slot, useRouter, useSegments } from 'expo-router';
import { useEffect, useRef } from 'react';
import { View } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AuthProvider, useAuth } from '@/auth/AuthContext';
import { FamilyGroupProvider, useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { ErrorBoundary } from '@/components/ErrorBoundary';
import { LoadingScreen } from '@/components/LoadingScreen';
import { OfflineBanner } from '@/components/OfflineBanner';
import { AppUpdateNotice } from '@/components/AppUpdateNotice';
import { familyGroupsApi, onboardingApi } from '@/api/endpoints';

function NavigationGuard() {
  const { state, user } = useAuth();
  const { clearGroup, restoreGroup } = useFamilyGroupContext();
  const router = useRouter();
  const segments = useSegments() as string[];
  const route = segments.slice(1).join('/');
  const lightHeader = ['profile', 'catalog', 'stock'].includes(route);
  const onboardingChecked = useRef(false);

  useEffect(() => {
    if (state !== 'authenticated') return;
    let current = true;
    familyGroupsApi.list()
      .then((response) => {
        if (current) return restoreGroup(response.data, () => current);
      })
      .catch(() => undefined);
    return () => { current = false; };
  }, [state, user?.id, restoreGroup]);

  useEffect(() => {
    if (state === 'initializing') return;

    const inAuthGroup = segments[0] === '(auth)';
    // El deep link de reset puede abrirse con una sesión ya iniciada en el dispositivo;
    // no debe expulsar al usuario antes de que pueda cambiar la contraseña.
    const isResetPasswordScreen = segments[0] === '(auth)' && segments[1] === 'reset-password';

    if (state === 'unauthenticated' && !inAuthGroup) {
      onboardingChecked.current = false;
      clearGroup();
      router.replace('/(auth)/login' as never);
    } else if (state === 'authenticated' && inAuthGroup && !isResetPasswordScreen) {
      router.replace('/(app)' as never);
    }
  }, [state, segments, router, clearGroup]);

  useEffect(() => {
    if (state !== 'authenticated' || onboardingChecked.current) return;
    if (segments[0] === '(auth)') return;
    if (segments[0] === '(app)' && segments[1] === 'onboarding') return;

    onboardingChecked.current = true;
    onboardingApi.status()
      .then((response) => {
        if (!response.data.complete) {
          router.replace('/(app)/onboarding' as never);
        }
      })
      .catch(() => undefined);
  }, [state, segments, router]);

  if (state === 'initializing') {
    return <LoadingScreen message="CocinaComidaControl" />;
  }

  return (
    <View style={{ flex: 1 }}>
      <StatusBar style={lightHeader ? 'dark' : 'light'} />
      <AppUpdateNotice />
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

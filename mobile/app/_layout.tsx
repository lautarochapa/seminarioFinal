import { Slot, useRouter, useSegments } from 'expo-router';
import { useEffect } from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AuthProvider, useAuth } from '@/auth/AuthContext';
import { FamilyGroupProvider } from '@/auth/FamilyGroupContext';
import { LoadingScreen } from '@/components/LoadingScreen';

function NavigationGuard() {
  const { state } = useAuth();
  const router = useRouter();
  const segments = useSegments();

  useEffect(() => {
    if (state === 'initializing') return;

    const inAuthGroup = segments[0] === '(auth)';

    if (state === 'unauthenticated' && !inAuthGroup) {
      router.replace('/(auth)/login' as never);
    } else if (state === 'authenticated' && inAuthGroup) {
      router.replace('/(app)' as never);
    }
  }, [state, segments, router]);

  if (state === 'initializing') {
    return <LoadingScreen message="CocinaComidaControl" />;
  }

  return <Slot />;
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

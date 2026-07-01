import { Stack } from 'expo-router';
import { COLORS } from '@/utils/theme';

export default function AppLayout() {
  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: COLORS.surface },
        headerTintColor: COLORS.primary,
        headerTitleStyle: { fontWeight: '700', color: COLORS.textPrimary },
        contentStyle: { backgroundColor: COLORS.background },
      }}
    >
      <Stack.Screen name="index" options={{ title: 'Inicio', headerShown: false }} />
      <Stack.Screen name="profile" options={{ title: 'Mi perfil' }} />
      <Stack.Screen name="groups/index" options={{ title: 'Grupos familiares' }} />
      <Stack.Screen name="groups/[id]" options={{ title: 'Detalle del grupo' }} />
    </Stack>
  );
}

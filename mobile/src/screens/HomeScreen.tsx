import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useAuth } from '@/auth/AuthContext';
import { ScreenContainer } from '@/components/ScreenContainer';
import { AppButton } from '@/components/AppButton';
import { ListItem } from '@/components/ListItem';
import { COLORS, FONT_SIZE, SPACING } from '@/utils/theme';

export function HomeScreen() {
  const { user, logout, isLoading } = useAuth();
  const router = useRouter();

  const displayName = user ? `${user.name} ${user.lastname}`.trim() : '';

  return (
    <ScreenContainer scroll>
      <View style={styles.greeting}>
        <Text style={styles.logo}>🌿</Text>
        <Text style={styles.hello}>Hola, {displayName || user?.email || ''}</Text>
        <Text style={styles.sub}>CocinaComidaControl</Text>
      </View>

      <View style={styles.section}>
        <ListItem
          title="Mi perfil"
          subtitle="Ver y editar datos personales"
          onPress={() => router.push('/(app)/profile')}
        />
        <ListItem
          title="Grupos familiares"
          subtitle="Gestionar tu grupo y miembros"
          onPress={() => router.push('/(app)/groups')}
        />
      </View>

      <View style={styles.logout}>
        <AppButton
          title="Cerrar sesión"
          variant="outline"
          onPress={logout}
          loading={isLoading}
        />
      </View>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  greeting: {
    alignItems: 'center',
    paddingVertical: SPACING.xl,
    gap: SPACING.xs,
  },
  logo: {
    fontSize: 48,
  },
  hello: {
    fontSize: FONT_SIZE.xl,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  sub: {
    fontSize: FONT_SIZE.sm,
    color: COLORS.textSecondary,
  },
  section: {
    gap: SPACING.xs,
    marginTop: SPACING.lg,
  },
  logout: {
    marginTop: SPACING.xl,
  },
});

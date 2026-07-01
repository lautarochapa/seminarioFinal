import React from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { AppLogo } from './AppLogo';
import { COLORS, FONT, SPACING } from '@/utils/theme';

interface LoadingScreenProps {
  message?: string;
  showLogo?: boolean;
}

export function LoadingScreen({ message, showLogo = false }: LoadingScreenProps) {
  return (
    <View style={styles.container} accessible accessibilityLabel={message ?? 'Cargando'}>
      {showLogo && (
        <View style={styles.logoWrap}>
          <AppLogo variant="large" />
        </View>
      )}
      <ActivityIndicator size="large" color={COLORS.primary} />
      {message ? <Text style={styles.message}>{message}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: COLORS.background,
    gap: SPACING.md,
  },
  logoWrap: {
    marginBottom: SPACING.xl,
    alignItems: 'center',
  },
  message: {
    fontSize: FONT.bodySize,
    color: COLORS.textSecondary,
    textAlign: 'center',
    paddingHorizontal: SPACING.lg,
  },
});

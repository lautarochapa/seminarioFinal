import React from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { COLORS, FONT_SIZE, SPACING } from '@/utils/theme';

interface LoadingScreenProps {
  message?: string;
}

export function LoadingScreen({ message }: LoadingScreenProps) {
  return (
    <View style={styles.container} accessible accessibilityLabel={message ?? 'Cargando'}>
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
  message: {
    fontSize: FONT_SIZE.md,
    color: COLORS.textSecondary,
    textAlign: 'center',
    paddingHorizontal: SPACING.lg,
  },
});

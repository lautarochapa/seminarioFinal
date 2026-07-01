import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { AppButton } from './AppButton';
import { COLORS, FONT_SIZE, SPACING } from '@/utils/theme';

interface ErrorStateProps {
  message: string;
  onRetry?: () => void;
  traceId?: string;
}

export function ErrorState({ message, onRetry, traceId }: ErrorStateProps) {
  return (
    <View style={styles.container}>
      <Text style={styles.icon}>⚠️</Text>
      <Text style={styles.message}>{message}</Text>
      {traceId ? (
        <Text style={styles.trace} selectable>
          trace: {traceId}
        </Text>
      ) : null}
      {onRetry ? (
        <AppButton
          title="Reintentar"
          onPress={onRetry}
          variant="outline"
          style={styles.button}
        />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: SPACING.xl,
    gap: SPACING.md,
  },
  icon: {
    fontSize: 40,
  },
  message: {
    fontSize: FONT_SIZE.md,
    color: COLORS.textPrimary,
    textAlign: 'center',
  },
  trace: {
    fontSize: FONT_SIZE.xs,
    color: COLORS.textHint,
    textAlign: 'center',
  },
  button: {
    marginTop: SPACING.sm,
    alignSelf: 'center',
    paddingHorizontal: SPACING.xl,
  },
});

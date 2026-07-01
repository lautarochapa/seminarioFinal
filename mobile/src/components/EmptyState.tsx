import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { AppButton } from './AppButton';
import { COLORS, FONT_SIZE, SPACING } from '@/utils/theme';

interface EmptyStateProps {
  message: string;
  actionTitle?: string;
  onAction?: () => void;
}

export function EmptyState({ message, actionTitle, onAction }: EmptyStateProps) {
  return (
    <View style={styles.container}>
      <Text style={styles.icon}>📭</Text>
      <Text style={styles.message}>{message}</Text>
      {actionTitle && onAction ? (
        <AppButton
          title={actionTitle}
          onPress={onAction}
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
    fontSize: 48,
  },
  message: {
    fontSize: FONT_SIZE.md,
    color: COLORS.textSecondary,
    textAlign: 'center',
  },
  button: {
    marginTop: SPACING.sm,
    alignSelf: 'center',
    paddingHorizontal: SPACING.xl,
  },
});

import React from 'react';
import { Pressable, StyleSheet, Text } from 'react-native';
import { COLORS, FONT, SPACING, TOUCH_TARGET } from '@/utils/theme';

interface Props {
  prompt: string;
  actionLabel: string;
  onPress: () => void;
}

export function AuthFooterLink({ prompt, actionLabel, onPress }: Props) {
  return (
    <Pressable
      style={styles.wrap}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${prompt} ${actionLabel}`}
    >
      <Text style={styles.text}>
        {prompt} <Text style={styles.action}>{actionLabel}</Text>
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  wrap: {
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: TOUCH_TARGET,
    paddingVertical: SPACING.sm,
  },
  text: {
    fontSize: FONT.bodySize,
    color: COLORS.textSecondary,
  },
  action: {
    color: COLORS.primary,
    fontWeight: '800',
  },
});

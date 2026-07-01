import React from 'react';
import {
  ActivityIndicator,
  StyleSheet,
  Text,
  TouchableOpacity,
  type TouchableOpacityProps,
} from 'react-native';
import { COLORS, FONT_SIZE, RADIUS, SPACING, TOUCH_TARGET } from '@/utils/theme';

interface AppButtonProps extends TouchableOpacityProps {
  title: string;
  loading?: boolean;
  variant?: 'primary' | 'outline' | 'ghost';
}

export function AppButton({
  title,
  loading = false,
  variant = 'primary',
  disabled,
  style,
  ...rest
}: AppButtonProps) {
  const isDisabled = disabled || loading;

  return (
    <TouchableOpacity
      accessibilityRole="button"
      accessibilityLabel={title}
      accessibilityState={{ disabled: isDisabled, busy: loading }}
      disabled={isDisabled}
      style={[
        styles.base,
        variant === 'primary' && styles.primary,
        variant === 'outline' && styles.outline,
        variant === 'ghost' && styles.ghost,
        isDisabled && styles.disabled,
        style,
      ]}
      {...rest}
    >
      {loading ? (
        <ActivityIndicator
          color={variant === 'primary' ? '#fff' : COLORS.primary}
          size="small"
        />
      ) : (
        <Text
          style={[
            styles.text,
            variant === 'primary' && styles.textPrimary,
            variant === 'outline' && styles.textOutline,
            variant === 'ghost' && styles.textGhost,
            isDisabled && styles.textDisabled,
          ]}
        >
          {title}
        </Text>
      )}
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  base: {
    minHeight: TOUCH_TARGET,
    borderRadius: RADIUS.md,
    paddingHorizontal: SPACING.lg,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primary: {
    backgroundColor: COLORS.primary,
  },
  outline: {
    backgroundColor: 'transparent',
    borderWidth: 1.5,
    borderColor: COLORS.primary,
  },
  ghost: {
    backgroundColor: 'transparent',
  },
  disabled: {
    opacity: 0.5,
  },
  text: {
    fontSize: FONT_SIZE.md,
    fontWeight: '600',
  },
  textPrimary: {
    color: '#fff',
  },
  textOutline: {
    color: COLORS.primary,
  },
  textGhost: {
    color: COLORS.primary,
  },
  textDisabled: {
    color: COLORS.disabled,
  },
});

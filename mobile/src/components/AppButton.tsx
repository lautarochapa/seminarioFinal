import React from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  type ViewStyle,
} from 'react-native';
import { COLORS, FONT, RADIUS, SPACING, TOUCH_TARGET } from '@/utils/theme';

export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';

interface AppButtonProps {
  title: string;
  onPress?: () => void;
  loading?: boolean;
  disabled?: boolean;
  variant?: ButtonVariant;
  style?: ViewStyle;
  fullWidth?: boolean;
  accessibilityLabel?: string;
}

export function AppButton({
  title,
  onPress,
  loading = false,
  disabled = false,
  variant = 'primary',
  style,
  fullWidth = false,
  accessibilityLabel,
}: AppButtonProps) {
  const isDisabled = disabled || loading;

  return (
    <Pressable
      onPress={onPress}
      disabled={isDisabled}
      accessible
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? title}
      accessibilityState={{ disabled: isDisabled, busy: loading }}
      style={({ pressed }) => [
        styles.base,
        variantStyle[variant],
        pressed && !isDisabled && pressedStyle[variant],
        isDisabled && styles.disabled,
        fullWidth && styles.fullWidth,
        style,
      ]}
    >
      {loading ? (
        <ActivityIndicator
          color={variant === 'primary' || variant === 'danger' ? '#fff' : COLORS.primary}
          size="small"
        />
      ) : (
        <Text style={[styles.text, textStyle[variant], isDisabled && styles.textDisabled]}>
          {title}
        </Text>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  base: {
    minHeight: TOUCH_TARGET,
    borderRadius: RADIUS.md,
    paddingHorizontal: SPACING.lg,
    paddingVertical: SPACING.sm,
    alignItems: 'center',
    justifyContent: 'center',
    alignSelf: 'flex-start',
  },
  fullWidth: {
    alignSelf: 'stretch',
  },
  disabled: {
    opacity: 0.5,
  },
  text: {
    fontSize: FONT.buttonSize,
    fontWeight: FONT.buttonWeight,
    letterSpacing: 0.1,
  },
  textDisabled: {
    opacity: 0.7,
  },
});

const variantStyle: Record<ButtonVariant, object> = {
  primary: { backgroundColor: COLORS.primary },
  secondary: { backgroundColor: COLORS.dark },
  outline: {
    backgroundColor: 'transparent',
    borderWidth: 1.5,
    borderColor: COLORS.primary,
  },
  ghost: { backgroundColor: 'transparent' },
  danger: { backgroundColor: COLORS.error },
};

const pressedStyle: Record<ButtonVariant, object> = {
  primary: { backgroundColor: COLORS.primaryDark },
  secondary: { backgroundColor: '#3A3B40' },
  outline: { backgroundColor: COLORS.primarySurface },
  ghost: { backgroundColor: COLORS.borderLight },
  danger: { backgroundColor: '#B91C1C' },
};

const textStyle: Record<ButtonVariant, object> = {
  primary: { color: '#fff' },
  secondary: { color: '#fff' },
  outline: { color: COLORS.primary },
  ghost: { color: COLORS.primary },
  danger: { color: '#fff' },
};

import React, { forwardRef } from 'react';
import {
  StyleSheet,
  Text,
  TextInput,
  View,
  type TextInputProps,
} from 'react-native';
import { COLORS, FONT_SIZE, RADIUS, SPACING } from '@/utils/theme';

interface AppInputProps extends TextInputProps {
  label?: string;
  error?: string;
}

export const AppInput = forwardRef<TextInput, AppInputProps>(
  function AppInput({ label, error, style, ...rest }, ref) {
    return (
      <View style={styles.container}>
        {label ? <Text style={styles.label}>{label}</Text> : null}
        <TextInput
          ref={ref}
          style={[styles.input, error ? styles.inputError : null, style]}
          placeholderTextColor={COLORS.textHint}
          accessibilityLabel={label}
          accessibilityHint={error}
          {...rest}
        />
        {error ? <Text style={styles.error}>{error}</Text> : null}
      </View>
    );
  },
);

const styles = StyleSheet.create({
  container: {
    marginBottom: SPACING.md,
  },
  label: {
    fontSize: FONT_SIZE.sm,
    color: COLORS.textSecondary,
    marginBottom: SPACING.xs,
    fontWeight: '500',
  },
  input: {
    minHeight: 48,
    borderWidth: 1.5,
    borderColor: COLORS.border,
    borderRadius: RADIUS.md,
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    fontSize: FONT_SIZE.md,
    color: COLORS.textPrimary,
    backgroundColor: COLORS.surface,
  },
  inputError: {
    borderColor: COLORS.error,
  },
  error: {
    fontSize: FONT_SIZE.xs,
    color: COLORS.error,
    marginTop: SPACING.xs,
  },
});

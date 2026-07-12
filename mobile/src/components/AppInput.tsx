import React, { forwardRef, useState } from 'react';
import {
  StyleSheet,
  Text,
  TextInput,
  View,
  type TextInputProps,
} from 'react-native';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

interface AppInputProps extends TextInputProps {
  label?: string;
  error?: string;
  hint?: string;
}

export const AppInput = forwardRef<TextInput, AppInputProps>(
  function AppInput({ label, error, hint, style, ...rest }, ref) {
    const [focused, setFocused] = useState(false);

    return (
      <View style={styles.container}>
        {label ? <Text style={styles.label}>{label}</Text> : null}
        <TextInput
          ref={ref}
          style={[
            styles.input,
            focused && styles.inputFocused,
            error ? styles.inputError : null,
            style,
          ]}
          placeholderTextColor={COLORS.textHint}
          accessibilityLabel={label}
          accessibilityHint={error ?? hint}
          onFocus={(e) => {
            setFocused(true);
            rest.onFocus?.(e);
          }}
          onBlur={(e) => {
            setFocused(false);
            rest.onBlur?.(e);
          }}
          {...rest}
        />
        {error ? <Text style={styles.error} accessibilityLiveRegion="polite">{error}</Text> : null}
        {!error && hint ? <Text style={styles.hint}>{hint}</Text> : null}
      </View>
    );
  },
);

const styles = StyleSheet.create({
  container: {
    marginBottom: SPACING.md,
  },
  label: {
    fontSize: FONT.labelSize,
    fontWeight: FONT.labelWeight,
    color: COLORS.textSecondary,
    marginBottom: SPACING.xs,
  },
  input: {
    minHeight: 50,
    borderWidth: 1.5,
    borderColor: COLORS.border,
    borderRadius: RADIUS.md,
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm + 2,
    fontSize: FONT.bodySize,
    color: COLORS.textPrimary,
    backgroundColor: COLORS.surface,
  },
  inputFocused: {
    borderColor: COLORS.primary,
    borderWidth: 2,
  },
  inputError: {
    borderColor: COLORS.error,
    borderWidth: 2,
  },
  error: {
    fontSize: FONT.captionSize,
    color: COLORS.error,
    marginTop: SPACING.xs,
    fontWeight: '500',
  },
  hint: {
    fontSize: FONT.captionSize,
    color: COLORS.textHint,
    marginTop: SPACING.xs,
  },
});

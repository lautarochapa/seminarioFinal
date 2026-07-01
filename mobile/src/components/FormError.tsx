import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { COLORS, FONT_SIZE, RADIUS, SPACING } from '@/utils/theme';

interface FormErrorProps {
  message: string | null | undefined;
}

export function FormError({ message }: FormErrorProps) {
  if (!message) return null;
  return (
    <View style={styles.container}>
      <Text style={styles.text}>{message}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: COLORS.errorLight,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    marginBottom: SPACING.md,
    borderLeftWidth: 3,
    borderLeftColor: COLORS.error,
  },
  text: {
    fontSize: FONT_SIZE.sm,
    color: COLORS.error,
  },
});

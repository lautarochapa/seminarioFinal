import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

export function ServiceBadge({ label, enabled }: { label: string; enabled: boolean }) {
  return (
    <View style={[styles.badge, enabled ? styles.enabled : styles.disabled]}>
      <Text style={[styles.text, enabled ? styles.enabledText : styles.disabledText]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: { borderRadius: RADIUS.full, paddingHorizontal: SPACING.sm, paddingVertical: 3 },
  enabled: { backgroundColor: COLORS.successLight },
  disabled: { backgroundColor: COLORS.borderLight },
  text: { fontSize: FONT.captionSize, fontWeight: '600' },
  enabledText: { color: COLORS.success },
  disabledText: { color: COLORS.textHint },
});

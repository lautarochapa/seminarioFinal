import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';
import { originLabel } from '@/utils/retail';
import type { DataOrigin } from '@/types/retail';

export function DataOriginBadge({ origin }: { origin?: DataOrigin | null }) {
  const label = originLabel(origin);
  const isDemo = label === 'demo';
  return (
    <View style={[styles.badge, isDemo && styles.demo]}>
      <Text style={[styles.text, isDemo && styles.demoText]}>Origen: {label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    alignSelf: 'flex-start',
    borderRadius: RADIUS.full,
    backgroundColor: COLORS.infoLight,
    paddingHorizontal: SPACING.sm,
    paddingVertical: 3,
  },
  demo: { backgroundColor: COLORS.warningLight },
  text: { color: COLORS.info, fontSize: FONT.captionSize, fontWeight: '600' },
  demoText: { color: COLORS.warning },
});

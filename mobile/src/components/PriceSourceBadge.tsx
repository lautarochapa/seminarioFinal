import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { COLORS, FONT, RADIUS } from '@/utils/theme';
import type { PriceSource } from '@/types/recipe';

const LABELS: Record<string, string> = {
  branch: 'Precio de la sucursal',
  chain: 'Mejor precio de la cadena',
  group_history: 'Último precio pagado',
  best_available: 'Mejor precio disponible',
  manual: 'Precio manual',
};

export function PriceSourceBadge({ source }: { source: PriceSource | 'manual' | null }) {
  if (!source) {
    return (
      <View style={[styles.badge, styles.none]}>
        <Text style={[styles.text, styles.noneText]}>Sin precio disponible</Text>
      </View>
    );
  }

  return (
    <View style={styles.badge}>
      <Text style={styles.text}>{LABELS[source] ?? source}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    alignSelf: 'flex-start',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: RADIUS.full,
    backgroundColor: COLORS.primarySurface,
  },
  text: { fontSize: FONT.captionSize, color: COLORS.primaryDark, fontWeight: '700' },
  none: { backgroundColor: COLORS.surfaceElevated },
  noneText: { color: COLORS.textHint },
});

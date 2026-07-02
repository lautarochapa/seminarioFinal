import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import type { UserReport } from '@/types/retail';

export function ReportMetricCard({ item }: { item: UserReport }) {
  const progress = item.progress !== null && item.progress !== undefined ? Math.max(0, Math.min(100, item.progress)) : null;
  return (
    <View style={styles.card}>
      <Text style={styles.title}>{item.title}</Text>
      <Text style={styles.value}>{item.value}</Text>
      {item.subtitle ? <Text style={styles.subtitle}>{item.subtitle}</Text> : null}
      {progress !== null ? (
        <View style={styles.track}>
          <View style={[styles.bar, { width: `${progress}%` }]} />
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.md, gap: SPACING.xs, ...SHADOW.sm },
  title: { fontSize: FONT.captionSize, color: COLORS.textSecondary, fontWeight: '700' },
  value: { fontSize: 22, color: COLORS.textPrimary, fontWeight: '800' },
  subtitle: { fontSize: FONT.captionSize, color: COLORS.textHint },
  track: { height: 8, borderRadius: RADIUS.full, backgroundColor: COLORS.borderLight, overflow: 'hidden', marginTop: SPACING.xs },
  bar: { height: 8, backgroundColor: COLORS.primary },
});

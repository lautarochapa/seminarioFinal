import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import { formatMoney } from '@/utils/retail';

interface Props {
  itemsAdded: number;
  estimatedTotal: number;
  itemsWithoutPrice: number;
  itemsUnmapped: number;
}

export function ShoppingGenerationSummary({ itemsAdded, estimatedTotal, itemsWithoutPrice, itemsUnmapped }: Props) {
  return (
    <View style={styles.card}>
      <Text style={styles.title}>Resumen de la generación</Text>
      <Row label="Productos agregados" value={String(itemsAdded)} />
      <Row label="Total estimado" value={formatMoney(estimatedTotal, 'ARS')} />
      {itemsWithoutPrice > 0 ? <Row label="Sin precio" value={String(itemsWithoutPrice)} warn /> : null}
      {itemsUnmapped > 0 ? <Row label="Sin mapear" value={String(itemsUnmapped)} warn /> : null}
    </View>
  );
}

function Row({ label, value, warn }: { label: string; value: string; warn?: boolean }) {
  return (
    <View style={styles.row}>
      <Text style={styles.label}>{label}</Text>
      <Text style={[styles.value, warn && styles.warnValue]}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.md, gap: SPACING.xs, ...SHADOW.sm },
  title: { fontSize: FONT.subtitleSize, fontWeight: '700', color: COLORS.textPrimary, marginBottom: SPACING.xs },
  row: { flexDirection: 'row', justifyContent: 'space-between' },
  label: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
  value: { fontSize: FONT.captionSize, color: COLORS.textPrimary, fontWeight: '700' },
  warnValue: { color: COLORS.warning },
});

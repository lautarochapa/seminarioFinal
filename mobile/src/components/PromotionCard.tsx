import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { DataOriginBadge } from '@/components/DataOriginBadge';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import { formatDate } from '@/utils/retail';
import type { Promotion } from '@/types/retail';

export function PromotionCard({ item }: { item: Promotion }) {
  return (
    <View style={styles.card}>
      <Text style={styles.title}>{item.name}</Text>
      {item.chain?.name ? <Text style={styles.meta}>{item.chain.name}{item.branch?.name ? ` · ${item.branch.name}` : ''}</Text> : null}
      {item.description ? <Text style={styles.meta}>{item.description}</Text> : null}
      <Text style={styles.discount}>{item.discount_type}: {item.discount_value ?? 'Sin valor'}</Text>
      <Text style={styles.meta}>Vigencia: {formatDate(item.valid_from)} - {formatDate(item.valid_to)}</Text>
      <Text style={styles.meta}>Dia: {item.day_of_week ?? 'Todos'}</Text>
      <Text style={styles.meta}>{item.requires_payment_method ? 'Requiere metodo de pago' : 'Sin metodo de pago requerido'}</Text>
      {item.payment_methods && item.payment_methods.length > 0 ? (
        <Text style={styles.meta}>Metodos: {item.payment_methods.map((pm) => pm.name).join(', ')}</Text>
      ) : null}
      <View style={styles.badges}>
        <DataOriginBadge origin="demo" />
        <Text style={styles.status}>{item.status}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.md, gap: SPACING.xs, ...SHADOW.sm },
  title: { fontSize: FONT.subtitleSize, fontWeight: '700', color: COLORS.textPrimary },
  discount: { fontSize: FONT.bodySize, fontWeight: '700', color: COLORS.primaryDark },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
  badges: { flexDirection: 'row', gap: SPACING.xs, alignItems: 'center', flexWrap: 'wrap' },
  status: { fontSize: FONT.captionSize, color: COLORS.textHint, fontWeight: '600' },
});

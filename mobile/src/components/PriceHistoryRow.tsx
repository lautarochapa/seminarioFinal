import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { DataOriginBadge } from '@/components/DataOriginBadge';
import { COLORS, FONT, SPACING } from '@/utils/theme';
import { formatDate, formatMoney } from '@/utils/retail';
import type { PriceHistoryEntry } from '@/types/retail';

export function PriceHistoryRow({ item }: { item: PriceHistoryEntry }) {
  return (
    <View style={styles.row}>
      <Text style={styles.price}>{formatMoney(item.price, item.currency)}</Text>
      <Text style={styles.meta}>Desde {formatDate(item.valid_from)} hasta {formatDate(item.valid_to)}</Text>
      <DataOriginBadge origin={item.source} />
    </View>
  );
}

const styles = StyleSheet.create({
  row: { gap: SPACING.xs, paddingVertical: SPACING.sm, borderBottomWidth: 1, borderBottomColor: COLORS.borderLight },
  price: { fontSize: FONT.bodySize, fontWeight: '700', color: COLORS.textPrimary },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
});

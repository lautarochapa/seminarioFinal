import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { PriceSourceBadge } from '@/components/PriceSourceBadge';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import { formatMoney } from '@/utils/retail';
import type { GeneratedShoppingListItem } from '@/types/recipe';

interface Props {
  item: GeneratedShoppingListItem;
  name: string;
  unitSymbol?: string | null;
}

export function EstimatedPriceRow({ item, name, unitSymbol }: Props) {
  return (
    <View style={styles.row}>
      <View style={styles.header}>
        <Text style={styles.name} numberOfLines={2}>{name}</Text>
        <Text style={styles.subtotal}>
          {item.estimated_subtotal !== null ? formatMoney(item.estimated_subtotal, 'ARS') : 'Sin precio'}
        </Text>
      </View>
      <Text style={styles.meta}>
        Comprar: {item.purchase_quantity} {unitSymbol ?? ''}
        {item.requested_quantity !== item.purchase_quantity ? ` (necesita ${item.requested_quantity})` : ''}
      </Text>
      {item.estimated_unit_price !== null ? (
        <Text style={styles.meta}>Precio unitario: {formatMoney(item.estimated_unit_price, 'ARS')}</Text>
      ) : null}
      <PriceSourceBadge source={item.price_source} />
    </View>
  );
}

const styles = StyleSheet.create({
  row: { backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.sm, gap: 4, ...SHADOW.sm },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: SPACING.sm },
  name: { flex: 1, fontSize: FONT.bodySize, fontWeight: '700', color: COLORS.textPrimary },
  subtotal: { fontSize: FONT.bodySize, fontWeight: '800', color: COLORS.primaryDark },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
});

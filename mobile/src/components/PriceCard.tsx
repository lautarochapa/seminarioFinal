import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { BestPriceBadge } from '@/components/BestPriceBadge';
import { DataOriginBadge } from '@/components/DataOriginBadge';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import { dataOriginForProduct, formatDate, formatMoney } from '@/utils/retail';
import type { SupermarketProduct } from '@/types/retail';

export function PriceCard({ item, best }: { item: SupermarketProduct; best?: boolean }) {
  return (
    <View style={[styles.card, best && styles.best]}>
      <View style={styles.row}>
        <Text style={styles.title} numberOfLines={2}>{item.product?.name || item.source_name || 'Producto'}</Text>
        {best ? <BestPriceBadge /> : null}
      </View>
      <Text style={styles.price}>{formatMoney(item.current_price?.price, item.current_price?.currency)}</Text>
      <Text style={styles.meta}>{item.branch?.chain?.name || 'Cadena'} - {item.branch?.name || 'Sucursal'}</Text>
      <Text style={styles.meta}>Actualizado: {formatDate(item.current_price?.scraped_at || item.current_price?.captured_at || item.last_scraped_at)}</Text>
      <View style={styles.badges}>
        <DataOriginBadge origin={dataOriginForProduct(item)} />
        {item.current_price?.valid_to ? <Text style={styles.expired}>Vencido</Text> : <Text style={styles.current}>Vigente</Text>}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.md, gap: SPACING.xs, ...SHADOW.sm },
  best: { borderWidth: 1, borderColor: COLORS.warning },
  row: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: SPACING.sm },
  title: { flex: 1, fontSize: FONT.bodySize, fontWeight: '700', color: COLORS.textPrimary },
  price: { fontSize: 20, fontWeight: '800', color: COLORS.primaryDark },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
  badges: { flexDirection: 'row', flexWrap: 'wrap', gap: SPACING.xs, alignItems: 'center' },
  current: { fontSize: FONT.captionSize, color: COLORS.success, fontWeight: '700' },
  expired: { fontSize: FONT.captionSize, color: COLORS.error, fontWeight: '700' },
});

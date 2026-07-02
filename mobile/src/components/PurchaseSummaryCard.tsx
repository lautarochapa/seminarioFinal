import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { StatusBadge } from './StatusBadge';
import { MoneyText } from './MoneyText';
import { COLORS, FONT, FONT_SIZE, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import type { Purchase } from '@/types/purchase';

interface PurchaseSummaryCardProps {
  purchase: Purchase;
  onPress: () => void;
}

export function PurchaseSummaryCard({ purchase, onPress }: PurchaseSummaryCardProps) {
  const date = purchase.purchase_date
    ? new Date(purchase.purchase_date).toLocaleDateString('es-AR')
    : null;

  return (
    <Pressable
      style={({ pressed }) => [styles.card, pressed && styles.pressed]}
      onPress={onPress}
      accessibilityRole="button"
    >
      <View style={styles.iconWrap}>
        <MaterialCommunityIcons name="receipt" size={22} color={COLORS.info} />
      </View>
      <View style={styles.body}>
        <View style={styles.row}>
          <Text style={styles.id} numberOfLines={1}>Compra #{purchase.id}</Text>
          <StatusBadge status={purchase.status} />
        </View>
        {date ? <Text style={styles.date}>{date}</Text> : null}
        <MoneyText amount={purchase.actual_total ?? purchase.estimated_total} style={styles.total} />
      </View>
      <MaterialCommunityIcons name="chevron-right" size={20} color={COLORS.textHint} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    gap: SPACING.md,
    ...SHADOW.sm,
  },
  pressed: { opacity: 0.8 },
  iconWrap: {
    width: 44,
    height: 44,
    borderRadius: RADIUS.sm,
    backgroundColor: COLORS.infoLight,
    alignItems: 'center',
    justifyContent: 'center',
  },
  body: { flex: 1, gap: 4 },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: SPACING.xs },
  id: { fontSize: FONT.bodySize, fontWeight: '600', color: COLORS.textPrimary, flex: 1 },
  date: { fontSize: FONT_SIZE.xs, color: COLORS.textSecondary },
  total: { fontSize: FONT.bodySize, fontWeight: '700', color: COLORS.textPrimary },
});

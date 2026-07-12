import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { StatusBadge } from './StatusBadge';
import { MoneyText } from './MoneyText';
import { COLORS, FONT, FONT_SIZE, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import type { ShoppingList } from '@/types/shopping';

interface ShoppingListCardProps {
  list: ShoppingList;
  onPress: () => void;
}

export function ShoppingListCard({ list, onPress }: ShoppingListCardProps) {
  const date = list.created_at ? new Date(list.created_at).toLocaleDateString('es-AR') : null;

  return (
    <Pressable
      style={({ pressed }) => [styles.card, pressed && styles.pressed]}
      onPress={onPress}
      accessibilityRole="button"
    >
      <View style={styles.iconWrap}>
        <MaterialCommunityIcons name="cart-outline" size={24} color={COLORS.primary} />
      </View>
      <View style={styles.body}>
        <View style={styles.row}>
          <Text style={styles.id} numberOfLines={1}>Lista #{list.id}</Text>
          <StatusBadge status={list.status} />
        </View>
        {list.source_type ? (
          <Text style={styles.meta} numberOfLines={1}>
            {list.source_type === 'manual' ? 'Manual' : list.source_type === 'meal_plan' ? 'Desde plan de comidas' : 'Desde historial'}
          </Text>
        ) : null}
        <View style={styles.footer}>
          {list.estimated_total != null ? (
            <MoneyText amount={list.estimated_total} style={styles.total} />
          ) : (
            <Text style={styles.meta}>Sin total estimado</Text>
          )}
          {date ? <Text style={styles.date}>{date}</Text> : null}
        </View>
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
    backgroundColor: COLORS.primarySurface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  body: { flex: 1, gap: 4 },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: SPACING.xs },
  id: { fontSize: FONT.bodySize, fontWeight: '600', color: COLORS.textPrimary, flex: 1 },
  meta: { fontSize: FONT_SIZE.xs, color: COLORS.textSecondary },
  footer: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  total: { fontSize: FONT.bodySize, fontWeight: '700', color: COLORS.textPrimary },
  date: { fontSize: FONT_SIZE.xs, color: COLORS.textHint },
});

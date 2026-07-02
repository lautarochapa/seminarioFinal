import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { COLORS, FONT, SPACING } from '@/utils/theme';
import type { RecipeIngredient } from '@/types/recipe';

export function RecipeIngredientRow({ item }: { item: RecipeIngredient }) {
  return (
    <View style={styles.row}>
      <Text style={styles.name}>{item.ingredient_name ?? 'Ingrediente'}</Text>
      <Text style={styles.qty}>
        {item.quantity ?? ''} {item.unit_name ?? ''}{item.is_optional ? ' · opcional' : ''}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { paddingVertical: SPACING.sm, borderBottomWidth: 1, borderBottomColor: COLORS.borderLight },
  name: { fontSize: FONT.bodySize, fontWeight: '600', color: COLORS.textPrimary },
  qty: { fontSize: FONT.captionSize, color: COLORS.textSecondary, marginTop: 2 },
});

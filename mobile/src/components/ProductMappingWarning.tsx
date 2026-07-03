import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';
import type { RecipeShoppingListUnmappedIngredient } from '@/types/recipe';

export function ProductMappingWarning({ item }: { item: RecipeShoppingListUnmappedIngredient }) {
  return (
    <View style={styles.row}>
      <MaterialCommunityIcons name="link-variant-off" size={16} color={COLORS.warning} />
      <Text style={styles.text}>
        {item.ingredient_name ?? 'Ingrediente'} no se pudo mapear a un producto o unidad.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: SPACING.xs, backgroundColor: COLORS.warningLight, borderRadius: RADIUS.sm, padding: SPACING.sm },
  text: { flex: 1, fontSize: FONT.captionSize, color: COLORS.textPrimary },
});

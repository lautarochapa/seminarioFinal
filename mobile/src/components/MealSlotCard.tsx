import React from 'react';
import { Pressable, StyleSheet, Text } from 'react-native';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';
import type { MealPlanEntry } from '@/types/mealPlan';

export function MealSlotCard({ entry, onPress }: { entry: MealPlanEntry; onPress?: () => void }) {
  const title = entry.meal_type?.name ?? 'Comida';
  const recipeName = entry.recipe?.name ?? entry.recipe?.nombre ?? entry.free_meal_description;
  return (
    <Pressable
      style={styles.card}
      onPress={onPress}
      disabled={!onPress}
      accessibilityRole={onPress ? 'button' : undefined}
      accessibilityLabel={`${title}: ${recipeName || 'sin receta asignada'}`}
    >
      <Text style={styles.slot}>{title}</Text>
      <Text style={styles.recipe} numberOfLines={2}>{recipeName || 'Sin receta asignada'}</Text>
      <Text style={styles.status}>{entry.status}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.sm, gap: 3, borderWidth: 1, borderColor: COLORS.borderLight },
  slot: { fontSize: FONT.captionSize, color: COLORS.textSecondary, fontWeight: '700' },
  recipe: { fontSize: FONT.bodySize, color: COLORS.textPrimary, fontWeight: '600' },
  status: { fontSize: FONT.captionSize, color: COLORS.primary },
});

import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { MealSlotCard } from './MealSlotCard';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import type { MealPlanEntry } from '@/types/mealPlan';

export function MealDayCard({ date, entries, onOpenRecipe }: { date: string; entries: MealPlanEntry[]; onOpenRecipe?: (id: number) => void }) {
  return (
    <View style={styles.card}>
      <Text style={styles.date}>{date}</Text>
      <View style={styles.slots}>
        {entries.length === 0 ? (
          <Text style={styles.empty}>Sin comidas planificadas.</Text>
        ) : entries.map((entry) => (
          <MealSlotCard key={entry.id} entry={entry} onPress={entry.recipe_id ? () => onOpenRecipe?.(entry.recipe_id as number) : undefined} />
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.md, gap: SPACING.sm, ...SHADOW.sm },
  date: { fontSize: FONT.subtitleSize, color: COLORS.textPrimary, fontWeight: '700' },
  slots: { gap: SPACING.sm },
  empty: { fontSize: FONT.captionSize, color: COLORS.textHint },
});

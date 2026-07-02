import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { FavoriteButton } from './FavoriteButton';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import type { RecipeSummary } from '@/types/recipe';

interface Props {
  recipe: RecipeSummary;
  favorite?: boolean;
  favoriteLoading?: boolean;
  onPress: () => void;
  onToggleFavorite?: () => void;
  badge?: string;
}

function totalMinutes(recipe: RecipeSummary): number | null {
  const total = (recipe.prep_time_minutes ?? 0) + (recipe.cook_time_minutes ?? 0);
  return total > 0 ? total : null;
}

export function RecipeCard({ recipe, favorite = false, favoriteLoading = false, onPress, onToggleFavorite, badge }: Props) {
  const minutes = totalMinutes(recipe);
  return (
    <Pressable style={({ pressed }) => [styles.card, pressed && { opacity: 0.85 }]} onPress={onPress}>
      <View style={styles.thumb}>
        <MaterialCommunityIcons name="silverware-fork-knife" size={28} color={COLORS.primary} />
      </View>
      <View style={styles.body}>
        <View style={styles.titleRow}>
          <Text style={styles.title} numberOfLines={2}>{recipe.name}</Text>
          {onToggleFavorite ? <FavoriteButton active={favorite} loading={favoriteLoading} onPress={onToggleFavorite} /> : null}
        </View>
        <View style={styles.metaRow}>
          {minutes ? <Text style={styles.meta}>{minutes} min</Text> : null}
          {recipe.servings ? <Text style={styles.meta}>{recipe.servings} porciones</Text> : null}
          {recipe.category?.name ? <Text style={styles.meta}>{recipe.category.name}</Text> : null}
        </View>
        {recipe.tags && recipe.tags.length > 0 ? (
          <Text style={styles.tags} numberOfLines={1}>{recipe.tags.map((t) => t.name).join(' · ')}</Text>
        ) : null}
        {badge ? <Text style={styles.badge}>{badge}</Text> : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: { flexDirection: 'row', gap: SPACING.md, backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.md, ...SHADOW.sm },
  thumb: { width: 56, height: 56, borderRadius: RADIUS.sm, backgroundColor: COLORS.primarySurface, alignItems: 'center', justifyContent: 'center' },
  body: { flex: 1, gap: 5 },
  titleRow: { flexDirection: 'row', alignItems: 'flex-start', gap: SPACING.sm },
  title: { flex: 1, fontSize: FONT.subtitleSize, fontWeight: '700', color: COLORS.textPrimary },
  metaRow: { flexDirection: 'row', flexWrap: 'wrap', gap: SPACING.sm },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
  tags: { fontSize: FONT.captionSize, color: COLORS.primaryDark },
  badge: { alignSelf: 'flex-start', fontSize: FONT.captionSize, color: COLORS.info, fontWeight: '700' },
});

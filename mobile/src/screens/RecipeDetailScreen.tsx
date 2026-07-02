import React from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { RecipeIngredientRow } from '@/components/RecipeIngredientRow';
import { RecipeStep } from '@/components/RecipeStep';
import { FavoriteButton } from '@/components/FavoriteButton';
import { useRecipeDetail } from '@/hooks/useRecipeDetail';
import { useRecipeFavorites } from '@/hooks/useRecipeFavorites';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';

export function RecipeDetailScreen({ recipeId }: { recipeId: number }) {
  const { data, loading, error, refresh } = useRecipeDetail(recipeId);
  const favorites = useRecipeFavorites();

  if (loading) return <LoadingScreen message="Cargando receta..." />;
  if (error) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Receta" showBack onBack={goBackOrHome} />
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" />
      </View>
    );
  }
  if (!data) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Receta" showBack onBack={goBackOrHome} />
        <EmptyState icon="chef-hat" message="No se encontró la receta." />
      </View>
    );
  }

  const minutes = (data.prep_time_minutes ?? 0) + (data.cook_time_minutes ?? 0);

  return (
    <View style={styles.fill}>
      <AppHeader
        title="Receta"
        showBack
        onBack={goBackOrHome}
        rightAction={<FavoriteButton active={favorites.favoriteIds.has(data.id)} loading={Boolean(favorites.savingIds[data.id])} onPress={() => favorites.toggle(data.id).catch(() => undefined)} />}
      />
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.hero}>
          <Text style={styles.title}>{data.name}</Text>
          {data.description ? <Text style={styles.desc}>{data.description}</Text> : null}
          <View style={styles.metaRow}>
            {minutes > 0 ? <Text style={styles.meta}>{minutes} min</Text> : null}
            {data.servings ? <Text style={styles.meta}>{data.servings} porciones</Text> : null}
            {data.category?.name ? <Text style={styles.meta}>{data.category.name}</Text> : null}
          </View>
          {data.tags && data.tags.length > 0 ? <Text style={styles.tags}>{data.tags.map((t) => t.name).join(' · ')}</Text> : null}
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Ingredientes</Text>
          {data.ingredients && data.ingredients.length > 0 ? data.ingredients.map((item) => <RecipeIngredientRow key={item.id} item={item} />) : <Text style={styles.emptyText}>Sin ingredientes cargados.</Text>}
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Pasos</Text>
          {data.steps && data.steps.length > 0 ? data.steps.map((step) => <RecipeStep key={step.step_number} step={step} />) : <Text style={styles.emptyText}>Sin pasos cargados.</Text>}
        </View>

        <AppButton title="Generar lista desde receta" disabled fullWidth />
        <Text style={styles.blocked}>No hay endpoint directo para generar lista desde receta. El backend expone generación desde meal plan.</Text>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  content: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
  hero: { backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.md, gap: SPACING.sm, ...SHADOW.sm },
  title: { fontSize: FONT.titleSize, fontWeight: '800', color: COLORS.textPrimary },
  desc: { fontSize: FONT.bodySize, color: COLORS.textSecondary, lineHeight: FONT.bodyLineHeight },
  metaRow: { flexDirection: 'row', flexWrap: 'wrap', gap: SPACING.sm },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary, fontWeight: '600' },
  tags: { fontSize: FONT.captionSize, color: COLORS.primaryDark },
  section: { backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.md, ...SHADOW.sm },
  sectionTitle: { fontSize: FONT.subtitleSize, fontWeight: '700', color: COLORS.textPrimary, marginBottom: SPACING.sm },
  emptyText: { color: COLORS.textHint },
  blocked: { fontSize: FONT.captionSize, color: COLORS.textSecondary, textAlign: 'center' },
});

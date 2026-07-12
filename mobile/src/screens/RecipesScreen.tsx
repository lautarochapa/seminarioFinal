import React, { useCallback, useState } from 'react';
import { FlatList, StyleSheet, View } from 'react-native';
import { useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { RecipeCard } from '@/components/RecipeCard';
import { RecipeFilters } from '@/components/RecipeFilters';
import { useRecipes } from '@/hooks/useRecipes';
import { useRecipeFavorites } from '@/hooks/useRecipeFavorites';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { SPACING, COLORS } from '@/utils/theme';
import type { RecipeSummary } from '@/types/recipe';

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

export function RecipesScreen() {
  const router = useRouter();
  const { data, loading, loadingMore, error, filters, setFilters, loadMore, refresh } = useRecipes();
  const favorites = useRecipeFavorites();
  const [search, setSearch] = useState(filters.search ?? '');

  const handleSearch = useCallback((value: string) => {
    setSearch(value);
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => setFilters({ ...filters, search: value || undefined }), 350);
  }, [filters, setFilters]);

  const openRecipe = (recipe: RecipeSummary) => {
    router.push({ pathname: '/(app)/recipes/[id]' as never, params: { id: String(recipe.id) } });
  };

  return (
    <View style={styles.fill}>
      <AppHeader title="Recetas" showBack onBack={goBackOrHome} />
      <View style={styles.filters}><RecipeFilters search={search} onSearch={handleSearch} /></View>
      {loading && data.length === 0 ? <LoadingScreen message="Cargando recetas..." /> : null}
      {error && data.length === 0 ? (
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type={error.isNetworkError ? 'network' : 'server'} />
      ) : (
        <FlatList
          data={data}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => (
            <RecipeCard
              recipe={item}
              favorite={favorites.favoriteIds.has(item.id)}
              favoriteLoading={Boolean(favorites.savingIds[item.id])}
              onToggleFavorite={() => favorites.toggle(item.id).catch(() => undefined)}
              onPress={() => openRecipe(item)}
            />
          )}
          contentContainerStyle={styles.list}
          onEndReached={loadMore}
          onEndReachedThreshold={0.3}
          onRefresh={() => { refresh(); favorites.refresh(); }}
          refreshing={loading && data.length > 0}
          ListEmptyComponent={!loading ? <EmptyState icon="chef-hat" message="No hay recetas para mostrar." /> : null}
          ListFooterComponent={loadingMore ? <LoadingScreen message="Cargando más..." /> : null}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  filters: { padding: SPACING.md, paddingBottom: SPACING.sm },
  list: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
});

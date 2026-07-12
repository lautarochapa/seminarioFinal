import React from 'react';
import { FlatList, StyleSheet, View } from 'react-native';
import { useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { RecipeCard } from '@/components/RecipeCard';
import { useRecipeFavorites } from '@/hooks/useRecipeFavorites';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, SPACING } from '@/utils/theme';

export function RecipeFavoritesScreen() {
  const router = useRouter();
  const { data, loading, error, refresh, favoriteIds, savingIds, toggle } = useRecipeFavorites();
  const recipes = data.map((f) => f.recipe).filter((r): r is NonNullable<typeof r> => Boolean(r));

  return (
    <View style={styles.fill}>
      <AppHeader title="Favoritos" showBack onBack={goBackOrHome} />
      {loading && recipes.length === 0 ? <LoadingScreen message="Cargando favoritos..." /> : error ? (
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" />
      ) : (
        <FlatList
          data={recipes}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => <RecipeCard recipe={item} favorite={favoriteIds.has(item.id)} favoriteLoading={Boolean(savingIds[item.id])} onToggleFavorite={() => toggle(item.id).catch(() => undefined)} onPress={() => router.push({ pathname: '/(app)/recipes/[id]' as never, params: { id: String(item.id) } })} />}
          contentContainerStyle={styles.list}
          onRefresh={refresh}
          refreshing={loading}
          ListEmptyComponent={<EmptyState icon="chef-hat" message="Todavía no tenés recetas favoritas." />}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
});

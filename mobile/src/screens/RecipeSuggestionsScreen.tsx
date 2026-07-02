import React from 'react';
import { FlatList, StyleSheet, View } from 'react-native';
import { useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { FamilyGroupSelector } from '@/components/FamilyGroupSelector';
import { LoadingScreen } from '@/components/LoadingScreen';
import { RecipeCard } from '@/components/RecipeCard';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useRecipeSuggestions } from '@/hooks/useRecipeSuggestions';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, SPACING } from '@/utils/theme';

export function RecipeSuggestionsScreen() {
  const router = useRouter();
  const { selectedGroup } = useFamilyGroupContext();
  const { data, loading, error, refresh } = useRecipeSuggestions(selectedGroup?.id ?? null);

  return (
    <View style={styles.fill}>
      <AppHeader title="Sugerencias" showBack onBack={goBackOrHome} />
      <FamilyGroupSelector />
      {loading ? <LoadingScreen message="Buscando sugerencias..." /> : error ? (
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" />
      ) : (
        <FlatList
          data={data}
          keyExtractor={(item) => String(item.recipe.id)}
          renderItem={({ item }) => <RecipeCard recipe={item.recipe} badge={item.reason ?? 'Sugerida'} onPress={() => router.push({ pathname: '/(app)/recipes/[id]' as never, params: { id: String(item.recipe.id) } })} />}
          contentContainerStyle={styles.list}
          onRefresh={refresh}
          refreshing={loading}
          ListEmptyComponent={<EmptyState icon="chef-hat" message="No hay sugerencias disponibles." />}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
});

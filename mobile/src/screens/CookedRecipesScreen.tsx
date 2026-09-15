import React from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppHeader } from '@/components/AppHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { useCookedRecipes } from '@/hooks/useCookedRecipes';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT_SIZE, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import type { CookedRecipeLog } from '@/types/recipe';

function formatDate(value: string | null): string {
  if (!value) return '';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
}

function CookedRow({ log, onPress }: { log: CookedRecipeLog; onPress: () => void }) {
  const name = log.recipe?.name ?? `Receta #${log.recipe_id}`;
  return (
    <Pressable
      style={({ pressed }) => [styles.card, pressed && styles.cardPressed]}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`Ver ${name}`}
    >
      <View style={styles.icon}>
        <MaterialCommunityIcons name="pot-steam-outline" size={22} color={COLORS.primary} />
      </View>
      <View style={styles.body}>
        <Text style={styles.name} numberOfLines={2}>{name}</Text>
        <Text style={styles.meta}>
          {formatDate(log.cooked_at)}
          {log.servings ? ` · ${log.servings} ${log.servings === 1 ? 'porción' : 'porciones'}` : ''}
          {log.stock_discounted ? ' · stock descontado' : ''}
        </Text>
      </View>
      <MaterialCommunityIcons name="chevron-right" size={20} color={COLORS.textHint} />
    </Pressable>
  );
}

export function CookedRecipesScreen() {
  const router = useRouter();
  const { data, loading, error, refresh } = useCookedRecipes();

  return (
    <View style={styles.fill}>
      <AppHeader title="Recetas cocinadas" showBack onBack={goBackOrHome} />
      {loading && data.length === 0 ? (
        <LoadingScreen message="Cargando historial..." />
      ) : error ? (
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" />
      ) : (
        <FlatList
          data={data}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => (
            <CookedRow
              log={item}
              onPress={() => router.push({ pathname: '/(app)/recipes/[id]' as never, params: { id: String(item.recipe_id) } })}
            />
          )}
          contentContainerStyle={styles.list}
          onRefresh={refresh}
          refreshing={loading}
          ListEmptyComponent={
            <EmptyState icon="pot-outline" message="Todavía no cocinaste ninguna receta." />
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: SPACING.md, gap: SPACING.sm, paddingBottom: SPACING.xxl },
  card: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.md,
    ...SHADOW.sm,
  },
  cardPressed: { opacity: 0.85 },
  icon: {
    width: 44,
    height: 44,
    borderRadius: RADIUS.md,
    backgroundColor: COLORS.primarySurface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  body: { flex: 1, gap: 3 },
  name: { fontSize: FONT_SIZE.md, fontWeight: '600', color: COLORS.textPrimary },
  meta: { fontSize: FONT_SIZE.xs, color: COLORS.textSecondary },
});

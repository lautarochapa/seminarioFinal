import React from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { FamilyGroupSelector } from '@/components/FamilyGroupSelector';
import { LoadingScreen } from '@/components/LoadingScreen';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useMealPlans } from '@/hooks/useMealPlans';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import type { MealPlan } from '@/types/mealPlan';

function MealPlanCard({ plan, onPress }: { plan: MealPlan; onPress: () => void }) {
  return (
    <Pressable style={({ pressed }) => [styles.card, pressed && { opacity: 0.85 }]} onPress={onPress}>
      <Text style={styles.title}>{plan.start_date} / {plan.end_date}</Text>
      <Text style={styles.meta}>{plan.period_type} · {plan.mode} · {plan.status}</Text>
      <Text style={styles.meta}>{plan.items?.length ?? 0} comidas</Text>
    </Pressable>
  );
}

export function MealPlansScreen() {
  const router = useRouter();
  const { addRecipeId, addRecipeName } = useLocalSearchParams<{ addRecipeId?: string; addRecipeName?: string }>();
  const { selectedGroup } = useFamilyGroupContext();
  const { data, loading, error, refresh } = useMealPlans(selectedGroup?.id ?? null);

  const openPlan = (id: number) => {
    const params: Record<string, string> = { id: String(id) };
    if (addRecipeId) params.addRecipeId = String(addRecipeId);
    if (addRecipeName) params.addRecipeName = String(addRecipeName);
    router.push({ pathname: '/(app)/meal-plans/[id]' as never, params });
  };

  return (
    <View style={styles.fill}>
      <AppHeader title="Meal plans" showBack onBack={goBackOrHome} />
      <FamilyGroupSelector />
      {addRecipeName ? <Text style={styles.banner}>Elegí un plan para agregar: {addRecipeName}</Text> : null}
      {!selectedGroup ? <EmptyState icon="account-group-outline" message="Seleccioná un grupo familiar." /> : loading ? <LoadingScreen message="Cargando meal plans..." /> : error ? (
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" />
      ) : (
        <FlatList
          data={data}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => <MealPlanCard plan={item} onPress={() => openPlan(item.id)} />}
          contentContainerStyle={styles.list}
          onRefresh={refresh}
          refreshing={loading}
          ListEmptyComponent={<EmptyState icon="clipboard-list-outline" message="No hay meal plans para este grupo." />}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
  card: { backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.md, gap: 4, ...SHADOW.sm },
  title: { fontSize: FONT.subtitleSize, fontWeight: '700', color: COLORS.textPrimary },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
  banner: { paddingHorizontal: SPACING.md, paddingVertical: SPACING.sm, color: COLORS.textSecondary, fontSize: FONT.captionSize },
});

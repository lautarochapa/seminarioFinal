import React, { useRef, useState } from 'react';
import { Alert, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { AppButton } from '@/components/AppButton';
import { WeekSelector } from '@/components/WeekSelector';
import { mealPlansApi } from '@/api/endpoints';
import { mealPlanLabel, weekRange } from '@/utils/mealPlan';
import { parseDateOnly } from '@/utils/formValues';
import { useSectionBackNavigation } from '@/hooks/useSectionBackNavigation';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { FamilyGroupSelector } from '@/components/FamilyGroupSelector';
import { LoadingScreen } from '@/components/LoadingScreen';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useMealPlans } from '@/hooks/useMealPlans';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import type { MealPlan } from '@/types/mealPlan';

function MealPlanCard({ plan, onPress }: { plan: MealPlan; onPress: () => void }) {
  return (
    <Pressable style={({ pressed }) => [styles.card, pressed && { opacity: 0.85 }]} onPress={onPress}>
      <Text style={styles.title}>{plan.start_date} / {plan.end_date}</Text>
      <Text style={styles.meta}>{mealPlanLabel(plan.period_type)} · {mealPlanLabel(plan.mode)} · {mealPlanLabel(plan.status)}</Text>
      <Text style={styles.meta}>{plan.items?.length ?? 0} comidas</Text>
    </Pressable>
  );
}

export function MealPlansScreen() {
  const router = useRouter();
  const { addRecipeId, addRecipeName, weekStart } = useLocalSearchParams<{ addRecipeId?: string; addRecipeName?: string; weekStart?: string }>();
  const goBack = useSectionBackNavigation(addRecipeId ? '/(app)/recipes/[id]' : '/(app)/planning', addRecipeId ? { id: addRecipeId } : {});
  const [weekOffset, setWeekOffset] = useState(0);
  const range = weekRange(weekOffset, (weekStart && parseDateOnly(weekStart)) || new Date());
  const [creating, setCreating] = useState(false);
  const creatingRef = useRef(false);
  const { selectedGroup } = useFamilyGroupContext();
  const { data, loading, error, refresh } = useMealPlans(selectedGroup?.id ?? null);

  const openPlan = (id: number) => {
    const params: Record<string, string> = { id: String(id) };
    if (addRecipeId) params.addRecipeId = String(addRecipeId);
    if (addRecipeName) params.addRecipeName = String(addRecipeName);
    router.push({ pathname: '/(app)/meal-plans/[id]' as never, params });
  };

  const createPlan = async () => {
    if (!selectedGroup || creatingRef.current || loading) return;
    const existing = data.find((plan) => plan.start_date.slice(0, 10) === range.start_date && plan.end_date.slice(0, 10) === range.end_date && !['cancelled', 'archived'].includes(plan.status));
    if (existing) { openPlan(existing.id); return; }
    creatingRef.current = true;
    setCreating(true);
    try {
      const response = await mealPlansApi.createPlan(selectedGroup.id, { period_type: 'weekly', ...range });
      openPlan(response.data.id);
    } catch {
      Alert.alert('No se pudo crear el plan', 'Revisá la conexión e intentá nuevamente.');
    } finally {
      creatingRef.current = false;
      setCreating(false);
    }
  };

  return (
    <View style={styles.fill}>
      <AppHeader title="Planes de comidas" showBack onBack={goBack} />
      <FamilyGroupSelector />
      {addRecipeName ? <Text style={styles.banner}>Elegí un plan para agregar: {addRecipeName}</Text> : null}
      {selectedGroup ? <View style={styles.create}>
        <WeekSelector label={`${range.start_date} / ${range.end_date}`} onPrev={() => setWeekOffset((v) => v - 1)} onCurrent={() => setWeekOffset(0)} onNext={() => setWeekOffset((v) => v + 1)} />
        <AppButton title="Crear plan semanal" onPress={createPlan} loading={creating} disabled={loading || Boolean(error)} fullWidth />
      </View> : null}
      {!selectedGroup ? <EmptyState icon="account-group-outline" message="Seleccioná un grupo familiar." /> : loading ? <LoadingScreen message="Cargando planes..." /> : error ? (
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" />
      ) : (
        <FlatList
          data={data}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => <MealPlanCard plan={item} onPress={() => openPlan(item.id)} />}
          contentContainerStyle={styles.list}
          onRefresh={refresh}
          refreshing={loading}
          ListEmptyComponent={<EmptyState icon="clipboard-list-outline" message="Todavía no hay planes de comidas." />}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  create: { padding: SPACING.md, gap: SPACING.sm },
  fill: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
  card: { backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.md, gap: 4, ...SHADOW.sm },
  title: { fontSize: FONT.subtitleSize, fontWeight: '700', color: COLORS.textPrimary },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
  banner: { paddingHorizontal: SPACING.md, paddingVertical: SPACING.sm, color: COLORS.textSecondary, fontSize: FONT.captionSize },
});

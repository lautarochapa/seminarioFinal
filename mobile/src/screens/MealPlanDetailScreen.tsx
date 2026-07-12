import React, { useState } from 'react';
import { Alert, FlatList, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { MealSlotCard } from '@/components/MealSlotCard';
import { GenerationSummary } from '@/components/GenerationSummary';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useMealPlanDetail } from '@/hooks/useMealPlanDetail';
import { mealPlansApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';

export function MealPlanDetailScreen({ planId }: { planId: number }) {
  const router = useRouter();
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;
  const { data, loading, error, refresh } = useMealPlanDetail(groupId, planId);
  const [generating, setGenerating] = useState(false);
  const [summary, setSummary] = useState<string | null>(null);

  async function handleGenerateList() {
    if (!groupId) return;
    setGenerating(true);
    setSummary(null);
    try {
      const res = await mealPlansApi.generateShoppingList(groupId, planId);
      const listId = res.data.shopping_list_id;
      setSummary('Lista generada desde el meal plan.');
      if (listId) {
        router.push({ pathname: '/(app)/shopping-lists/[id]' as never, params: { id: String(listId) } });
      }
    } catch (err) {
      const msg = err instanceof ApiError ? err.normalized.message : 'No se pudo generar la lista.';
      Alert.alert('Error', msg);
    } finally {
      setGenerating(false);
    }
  }

  if (loading) return <LoadingScreen message="Cargando meal plan..." />;
  if (error) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Meal plan" showBack onBack={goBackOrHome} />
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" />
      </View>
    );
  }

  return (
    <View style={styles.fill}>
      <AppHeader title="Meal plan" showBack onBack={goBackOrHome} />
      {data ? (
        <FlatList
          data={data.items ?? []}
          keyExtractor={(item) => String(item.id)}
          ListHeaderComponent={
            <View style={styles.headerCard}>
              <Text style={styles.title}>{data.start_date} / {data.end_date}</Text>
              <Text style={styles.meta}>{data.period_type} · {data.mode} · {data.status}</Text>
              <AppButton title={generating ? 'Generando...' : 'Generar lista'} onPress={handleGenerateList} loading={generating} fullWidth />
              {summary ? <GenerationSummary message={summary} /> : null}
            </View>
          }
          renderItem={({ item }) => <MealSlotCard entry={item} onPress={item.recipe_id ? () => router.push({ pathname: '/(app)/recipes/[id]' as never, params: { id: String(item.recipe_id) } }) : undefined} />}
          contentContainerStyle={styles.list}
          onRefresh={refresh}
          refreshing={loading}
          ListEmptyComponent={<EmptyState icon="clipboard-list-outline" message="Sin entradas en este plan." />}
        />
      ) : <EmptyState icon="clipboard-list-outline" message="No se encontró el meal plan." />}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
  headerCard: { backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.md, gap: SPACING.md, ...SHADOW.sm },
  title: { fontSize: FONT.titleSize, fontWeight: '800', color: COLORS.textPrimary },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
});

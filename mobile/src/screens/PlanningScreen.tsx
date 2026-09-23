import React, { useMemo, useState } from 'react';
import { AppButton } from '@/components/AppButton';
import { entriesInWeek, weekRange } from '@/utils/mealPlan';
import { FlatList, StyleSheet, View } from 'react-native';
import { useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { FamilyGroupSelector } from '@/components/FamilyGroupSelector';
import { LoadingScreen } from '@/components/LoadingScreen';
import { MealDayCard } from '@/components/MealDayCard';
import { WeekSelector } from '@/components/WeekSelector';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { usePlanning } from '@/hooks/usePlanning';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, SPACING } from '@/utils/theme';

export function PlanningScreen() {
  const router = useRouter();
  const { selectedGroup } = useFamilyGroupContext();
  const [weekOffset, setWeekOffset] = useState(0);
  const { start_date: start, end_date: end } = weekRange(weekOffset);
  const { data, loading, error, refresh } = usePlanning(selectedGroup?.id ?? null, start, end);

  const grouped = useMemo(() => {
    return entriesInWeek(data, start, end);
  }, [data, start, end]);

  return (
    <View style={styles.fill}>
      <AppHeader title="Planificación" showBack onBack={goBackOrHome} />
      <FamilyGroupSelector />
      <View style={styles.week}>
        <WeekSelector label={`${start} / ${end}`} onPrev={() => setWeekOffset((v) => v - 1)} onCurrent={() => setWeekOffset(0)} onNext={() => setWeekOffset((v) => v + 1)} />
        {selectedGroup ? <AppButton title="Administrar planes" variant="outline" onPress={() => router.push({ pathname: '/(app)/meal-plans' as never, params: { weekStart: start } })} /> : null}
      </View>
      {!selectedGroup ? <EmptyState icon="account-group-outline" message="Seleccioná un grupo familiar." /> : loading ? <LoadingScreen message="Cargando planificación..." /> : error ? (
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" />
      ) : (
        <FlatList
          data={grouped}
          keyExtractor={([date]) => date}
          renderItem={({ item }) => <MealDayCard date={item[0]} entries={item[1]} onOpenRecipe={(id) => router.push({ pathname: '/(app)/recipes/[id]' as never, params: { id: String(id), returnTo: 'planning' } })} />}
          contentContainerStyle={styles.list}
          onRefresh={refresh}
          refreshing={loading}
          ListEmptyComponent={<EmptyState icon="clipboard-list-outline" message="No hay comidas planificadas." />}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  week: { padding: SPACING.md, paddingBottom: SPACING.sm },
  list: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
});

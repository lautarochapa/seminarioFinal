import React, { useMemo, useState } from 'react';
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
import type { MealPlanEntry } from '@/types/mealPlan';

function isoDate(date: Date): string {
  return date.toISOString().slice(0, 10);
}

function startOfWeek(offset: number): Date {
  const date = new Date();
  date.setHours(12, 0, 0, 0);
  const day = date.getDay() || 7;
  date.setDate(date.getDate() - day + 1 + offset * 7);
  return date;
}

function weekLabel(offset: number): string {
  const start = startOfWeek(offset);
  const end = new Date(start);
  end.setDate(start.getDate() + 6);
  return `${isoDate(start)} / ${isoDate(end)}`;
}

export function PlanningScreen() {
  const router = useRouter();
  const { selectedGroup } = useFamilyGroupContext();
  const { data, loading, error, refresh } = usePlanning(selectedGroup?.id ?? null);
  const [weekOffset, setWeekOffset] = useState(0);
  const activePlan = data[0] ?? null;

  const grouped = useMemo(() => {
    const map: Record<string, MealPlanEntry[]> = {};
    const entries = activePlan?.items ?? [];
    entries.forEach((entry) => {
      if (!map[entry.date]) map[entry.date] = [];
      map[entry.date].push(entry);
    });
    return Object.entries(map).sort(([a], [b]) => a.localeCompare(b));
  }, [activePlan]);

  return (
    <View style={styles.fill}>
      <AppHeader title="Planificación" showBack onBack={goBackOrHome} />
      <FamilyGroupSelector />
      <View style={styles.week}>
        <WeekSelector label={weekLabel(weekOffset)} onPrev={() => setWeekOffset((v) => v - 1)} onCurrent={() => setWeekOffset(0)} onNext={() => setWeekOffset((v) => v + 1)} />
      </View>
      {!selectedGroup ? <EmptyState icon="account-group-outline" message="Seleccioná un grupo familiar." /> : loading ? <LoadingScreen message="Cargando planificación..." /> : error ? (
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" />
      ) : (
        <FlatList
          data={grouped}
          keyExtractor={([date]) => date}
          renderItem={({ item }) => <MealDayCard date={item[0]} entries={item[1]} onOpenRecipe={(id) => router.push({ pathname: '/(app)/recipes/[id]' as never, params: { id: String(id) } })} />}
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

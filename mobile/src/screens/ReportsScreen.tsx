import React, { useState } from 'react';
import { FlatList, StyleSheet, View } from 'react-native';
import { AppHeader } from '@/components/AppHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { FamilyGroupSelector } from '@/components/FamilyGroupSelector';
import { LoadingScreen } from '@/components/LoadingScreen';
import { PeriodSelector } from '@/components/PeriodSelector';
import { ReportMetricCard } from '@/components/ReportMetricCard';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useReports } from '@/hooks/useReports';
import { friendlyMessage } from '@/utils/errorParser';
import { goBackOrHome } from '@/utils/navigation';
import { COLORS, SPACING } from '@/utils/theme';
import type { ReportPeriod } from '@/types/retail';

export function ReportsScreen() {
  const { selectedGroup } = useFamilyGroupContext();
  const [period, setPeriod] = useState<ReportPeriod>('month');
  const reports = useReports(selectedGroup?.id ?? null, period);

  return (
    <View style={styles.fill}>
      <AppHeader title="Reportes" subtitle={selectedGroup?.name} showBack onBack={goBackOrHome} />
      <View style={styles.top}>
        <FamilyGroupSelector />
        <PeriodSelector value={period} onChange={setPeriod} />
      </View>
      {!selectedGroup ? <EmptyState icon="account-group-outline" message="Selecciona un grupo familiar." /> : null}
      {reports.loading && selectedGroup ? <LoadingScreen message="Cargando reportes..." /> : null}
      {reports.error ? <ErrorState message={friendlyMessage(reports.error)} traceId={reports.error.traceId} onRetry={reports.refresh} type="server" /> : null}
      {selectedGroup && !reports.loading && !reports.error ? (
        <FlatList
          data={reports.data ?? []}
          keyExtractor={(item) => item.key}
          renderItem={({ item }) => <ReportMetricCard item={item} />}
          ListEmptyComponent={<EmptyState icon="chart-box-outline" message="No hay datos de reporte para este periodo." />}
          contentContainerStyle={styles.list}
        />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  top: { padding: SPACING.md, gap: SPACING.md },
  list: { padding: SPACING.md, gap: SPACING.md },
});

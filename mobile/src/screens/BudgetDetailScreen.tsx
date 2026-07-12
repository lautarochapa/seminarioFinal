import React from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, Text, View } from 'react-native';
import { AppHeader } from '@/components/AppHeader';
import { MoneyText } from '@/components/MoneyText';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useBudgetSummary } from '@/hooks/useBudgetSummary';
import { useBudgetProjection } from '@/hooks/useBudgetProjection';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, FONT_SIZE, RADIUS, SHADOW, SPACING } from '@/utils/theme';

interface Props {
  budgetId: number;
}

const MONTHS = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

export function BudgetDetailScreen({ budgetId }: Props) {
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;
  const { data: summary, loading: loadingSummary, error: summaryError, refresh: refreshSummary } = useBudgetSummary(groupId, budgetId);
  const { data: projection, loading: loadingProjection } = useBudgetProjection(groupId, budgetId);

  if (loadingSummary) return <LoadingScreen message="Cargando presupuesto..." />;

  if (summaryError) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Presupuesto" showBack onBack={goBackOrHome} />
        <ErrorState message={friendlyMessage(summaryError)} traceId={summaryError.traceId ?? ''} onRetry={refreshSummary} type="server" />
      </View>
    );
  }

  if (!summary) return null;

  const percent = summary.consumed_percent;
  const barColor = percent >= 90 ? COLORS.error : percent >= 70 ? COLORS.warning : COLORS.primary;

  return (
    <View style={styles.fill}>
      <AppHeader
        title={`${MONTHS[summary.month]} ${summary.year}`}
        subtitle={selectedGroup?.name}
        showBack
        onBack={goBackOrHome}
      />
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        {/* Summary card */}
        <View style={styles.card}>
          <Text style={styles.sectionTitle}>Resumen</Text>
          <View style={styles.row}>
            <Text style={styles.label}>Total presupuestado</Text>
            <MoneyText amount={summary.total_amount} currency={summary.currency} style={styles.value} />
          </View>
          <View style={styles.row}>
            <Text style={styles.label}>Gastado</Text>
            <MoneyText amount={summary.spent_amount} currency={summary.currency} style={{ fontSize: FONT.bodySize, fontWeight: '600', color: barColor }} />
          </View>
          <View style={styles.row}>
            <Text style={styles.label}>Disponible</Text>
            <MoneyText amount={summary.available_amount} currency={summary.currency} style={styles.value} />
          </View>
          <View style={styles.barBg}>
            <View style={[styles.barFill, { width: `${Math.min(100, percent)}%` as `${number}%`, backgroundColor: barColor }]} />
          </View>
          <Text style={[styles.percent, { color: barColor }]}>{percent.toFixed(1)}% utilizado · {summary.purchase_count} compras</Text>
        </View>

        {/* Projection */}
        <View style={styles.card}>
          <View style={styles.row}>
            <Text style={styles.sectionTitle}>Proyección</Text>
            {loadingProjection && <ActivityIndicator size="small" color={COLORS.primary} />}
          </View>
          {projection ? (
            <>
              <View style={styles.row}>
                <Text style={styles.label}>Ya gastado</Text>
                <MoneyText amount={projection.spent_amount} currency={projection.currency} style={styles.value} />
              </View>
              {projection.planned_amount != null && (
                <View style={styles.row}>
                  <Text style={styles.label}>Planificado (listas activas)</Text>
                  <MoneyText amount={projection.planned_amount} currency={projection.currency} style={styles.value} />
                </View>
              )}
              <View style={styles.row}>
                <Text style={styles.label}>Disponible proyectado</Text>
                <MoneyText amount={projection.available_projected} currency={projection.currency} style={{ fontSize: FONT.bodySize, fontWeight: '600', color: projection.available_projected >= 0 ? COLORS.success : COLORS.error }} />
              </View>
              <View style={styles.barBg}>
                <View style={[styles.barFill, {
                  width: `${Math.min(100, projection.percent_projected)}%` as `${number}%`,
                  backgroundColor: projection.percent_projected >= 90 ? COLORS.error : projection.percent_projected >= 70 ? COLORS.warning : COLORS.info,
                }]} />
              </View>
              <Text style={styles.percent}>{projection.percent_projected.toFixed(1)}% proyectado</Text>
              {projection.planned_sources.length > 0 && (
                <View style={styles.sourcesSection}>
                  <Text style={styles.sourcesTitle}>Fuentes planificadas</Text>
                  {projection.planned_sources.map((s) => (
                    <View key={s.shopping_list_id} style={styles.sourceRow}>
                      <Text style={styles.sourceLabel}>
                        Lista #{s.shopping_list_id} ({s.source_type === 'meal_plan' ? 'Plan de comidas' : s.source_type === 'history' ? 'Historial' : 'Manual'})
                      </Text>
                      <MoneyText amount={s.estimated_total} currency={projection.currency} style={styles.sourceValue} />
                    </View>
                  ))}
                </View>
              )}
            </>
          ) : !loadingProjection ? (
            <Text style={styles.noProjection}>No hay datos de proyección disponibles.</Text>
          ) : null}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  scroll: { flex: 1 },
  content: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
  card: { backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.md, gap: SPACING.sm, ...SHADOW.sm },
  sectionTitle: { fontSize: FONT.subtitleSize, fontWeight: FONT.subtitleWeight, color: COLORS.textPrimary },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  label: { fontSize: FONT.labelSize, color: COLORS.textSecondary, flex: 1 },
  value: { fontSize: FONT.bodySize, fontWeight: '600', color: COLORS.textPrimary },
  barBg: { height: 8, backgroundColor: COLORS.border, borderRadius: RADIUS.full, overflow: 'hidden', marginTop: SPACING.xs },
  barFill: { height: 8, borderRadius: RADIUS.full },
  percent: { fontSize: FONT_SIZE.xs, fontWeight: '600', color: COLORS.textSecondary, textAlign: 'right' },
  noProjection: { fontSize: FONT_SIZE.xs, color: COLORS.textHint, fontStyle: 'italic' },
  sourcesSection: { gap: SPACING.xs, marginTop: SPACING.xs },
  sourcesTitle: { fontSize: FONT_SIZE.xs, fontWeight: '600', color: COLORS.textSecondary },
  sourceRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  sourceLabel: { fontSize: FONT_SIZE.xs, color: COLORS.textSecondary, flex: 1 },
  sourceValue: { fontSize: FONT_SIZE.xs, fontWeight: '600', color: COLORS.textPrimary },
});

import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { MoneyText } from './MoneyText';
import { COLORS, FONT, FONT_SIZE, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import type { Budget } from '@/types/budget';

interface BudgetProgressCardProps {
  budget: Budget;
  onPress?: () => void;
}

const MONTHS = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

export function BudgetProgressCard({ budget }: BudgetProgressCardProps) {
  const percent = budget.consumed_percent ?? 0;
  const barColor = percent >= 90 ? COLORS.error : percent >= 70 ? COLORS.warning : COLORS.primary;

  return (
    <View style={styles.card}>
      <View style={styles.header}>
        <Text style={styles.title}>{MONTHS[budget.month]} {budget.year}</Text>
        <Text style={styles.currency}>{budget.currency}</Text>
      </View>
      <View style={styles.amounts}>
        <MoneyText amount={budget.total_amount} style={styles.total} />
        {budget.used_amount != null && (
          <Text style={styles.used}>
            Gastado: <MoneyText amount={budget.used_amount} style={styles.usedValue} />
          </Text>
        )}
      </View>
      {budget.consumed_percent != null && (
        <>
          <View style={styles.barBg}>
            <View style={[styles.barFill, { width: `${Math.min(100, percent)}%` as `${number}%`, backgroundColor: barColor }]} />
          </View>
          <Text style={[styles.percent, { color: barColor }]}>{percent.toFixed(1)}% utilizado</Text>
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    gap: SPACING.sm,
    ...SHADOW.sm,
  },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  title: { fontSize: FONT.subtitleSize, fontWeight: FONT.subtitleWeight, color: COLORS.textPrimary },
  currency: { fontSize: FONT_SIZE.xs, color: COLORS.textHint, fontWeight: '600' },
  amounts: { gap: 2 },
  total: { fontSize: FONT.titleSize, fontWeight: '700', color: COLORS.textPrimary },
  used: { fontSize: FONT_SIZE.xs, color: COLORS.textSecondary },
  usedValue: { fontSize: FONT_SIZE.xs, fontWeight: '600', color: COLORS.textPrimary },
  barBg: { height: 8, backgroundColor: COLORS.border, borderRadius: RADIUS.full, overflow: 'hidden' },
  barFill: { height: 8, borderRadius: RADIUS.full },
  percent: { fontSize: FONT_SIZE.xs, fontWeight: '600', textAlign: 'right' },
});

import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';
import type { ReportPeriod } from '@/types/retail';

const OPTIONS: { value: ReportPeriod; label: string }[] = [
  { value: 'month', label: 'Mes' },
  { value: 'quarter', label: 'Trimestre' },
  { value: 'year', label: 'Ano' },
];

export function PeriodSelector({ value, onChange }: { value: ReportPeriod; onChange: (value: ReportPeriod) => void }) {
  return (
    <View style={styles.wrap}>
      {OPTIONS.map((option) => {
        const selected = value === option.value;
        return (
          <Pressable
            key={option.value}
            onPress={() => onChange(option.value)}
            style={[styles.btn, selected && styles.selected]}
            accessibilityRole="button"
            accessibilityState={{ selected }}
            accessibilityLabel={option.label}
          >
            <Text style={[styles.text, selected && styles.selectedText]}>
              {selected ? '✓ ' : ''}{option.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flexDirection: 'row', backgroundColor: COLORS.borderLight, borderRadius: RADIUS.md, padding: 3, gap: 3 },
  btn: { flex: 1, alignItems: 'center', paddingVertical: SPACING.sm, borderRadius: RADIUS.sm },
  selected: { backgroundColor: COLORS.surface },
  text: { fontSize: FONT.captionSize, fontWeight: '700', color: COLORS.textSecondary },
  selectedText: { color: COLORS.primaryDark },
});

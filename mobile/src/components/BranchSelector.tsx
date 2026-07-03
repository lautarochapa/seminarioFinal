import React from 'react';
import { ScrollView, StyleSheet, Text, Pressable } from 'react-native';
import { useBranches } from '@/hooks/useBranches';
import { COLORS, FONT, RADIUS, SPACING, TOUCH_TARGET } from '@/utils/theme';

interface Props {
  chainId: number;
  selectedBranchId: number | null;
  onSelect: (branchId: number | null) => void;
}

export function BranchSelector({ chainId, selectedBranchId, onSelect }: Props) {
  const { data: branches, loading } = useBranches({ chain_id: chainId });

  if (loading || branches.length === 0) return null;

  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.row}>
      <Pressable
        style={[styles.chip, selectedBranchId === null && styles.chipSelected]}
        onPress={() => onSelect(null)}
        accessibilityRole="button"
        accessibilityState={{ selected: selectedBranchId === null }}
        accessibilityLabel="Cualquier sucursal de la cadena"
      >
        <Text style={[styles.chipText, selectedBranchId === null && styles.chipTextSelected]}>Cualquier sucursal</Text>
      </Pressable>
      {branches.map((branch) => {
        const selected = selectedBranchId === branch.id;
        return (
          <Pressable
            key={branch.id}
            style={[styles.chip, selected && styles.chipSelected]}
            onPress={() => onSelect(branch.id)}
            accessibilityRole="button"
            accessibilityState={{ selected }}
            accessibilityLabel={branch.name}
          >
            <Text style={[styles.chipText, selected && styles.chipTextSelected]} numberOfLines={1}>{branch.name}</Text>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  row: { gap: SPACING.xs, paddingVertical: 2 },
  chip: {
    minHeight: TOUCH_TARGET - 8,
    paddingHorizontal: SPACING.md,
    justifyContent: 'center',
    borderRadius: RADIUS.full,
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.surface,
  },
  chipSelected: { borderColor: COLORS.primary, backgroundColor: COLORS.primarySurface },
  chipText: { fontSize: FONT.captionSize, color: COLORS.textSecondary, fontWeight: '600' },
  chipTextSelected: { color: COLORS.primaryDark, fontWeight: '800' },
});

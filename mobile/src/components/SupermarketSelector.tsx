import React from 'react';
import { ScrollView, StyleSheet, Text, Pressable } from 'react-native';
import { useSupermarkets } from '@/hooks/useSupermarkets';
import { COLORS, FONT, RADIUS, SPACING, TOUCH_TARGET } from '@/utils/theme';

interface Props {
  selectedChainId: number | null;
  onSelect: (chainId: number | null) => void;
}

export function SupermarketSelector({ selectedChainId, onSelect }: Props) {
  const { data: chains, loading } = useSupermarkets();

  if (loading || chains.length === 0) return null;

  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.row}>
      <Pressable
        style={[styles.chip, selectedChainId === null && styles.chipSelected]}
        onPress={() => onSelect(null)}
        accessibilityRole="button"
        accessibilityState={{ selected: selectedChainId === null }}
        accessibilityLabel="Sin cadena seleccionada"
      >
        <Text style={[styles.chipText, selectedChainId === null && styles.chipTextSelected]}>Cualquiera</Text>
      </Pressable>
      {chains.map((chain) => {
        const selected = selectedChainId === chain.id;
        return (
          <Pressable
            key={chain.id}
            style={[styles.chip, selected && styles.chipSelected]}
            onPress={() => onSelect(chain.id)}
            accessibilityRole="button"
            accessibilityState={{ selected }}
            accessibilityLabel={chain.name}
          >
            <Text style={[styles.chipText, selected && styles.chipTextSelected]} numberOfLines={1}>{chain.name}</Text>
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

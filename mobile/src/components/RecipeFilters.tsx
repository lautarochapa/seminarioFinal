import React from 'react';
import { StyleSheet, TextInput, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { COLORS, RADIUS, SPACING } from '@/utils/theme';

export function RecipeFilters({ search, onSearch }: { search: string; onSearch: (value: string) => void }) {
  return (
    <View style={styles.search}>
      <MaterialCommunityIcons name="magnify" size={20} color={COLORS.textHint} />
      <TextInput
        value={search}
        onChangeText={onSearch}
        placeholder="Buscar recetas..."
        placeholderTextColor={COLORS.textHint}
        style={styles.input}
        autoCapitalize="none"
        autoCorrect={false}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  search: { flexDirection: 'row', alignItems: 'center', gap: SPACING.sm, backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, paddingHorizontal: SPACING.md, minHeight: 44 },
  input: { flex: 1, color: COLORS.textPrimary },
});

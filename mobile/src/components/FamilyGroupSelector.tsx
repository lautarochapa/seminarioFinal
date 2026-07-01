import React from 'react';
import { Pressable, StyleSheet, Text, View, ActivityIndicator } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useFamilyGroups } from '@/hooks/useFamilyGroups';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';

export function FamilyGroupSelector() {
  const { selectedGroup, selectGroup } = useFamilyGroupContext();
  const { data: groups, loading, error } = useFamilyGroups();
  const router = useRouter();

  if (loading && groups.length === 0) {
    return (
      <View style={styles.container}>
        <ActivityIndicator size="small" color={COLORS.primary} />
        <Text style={styles.loadingText}>Cargando grupos...</Text>
      </View>
    );
  }

  if (error && groups.length === 0) {
    return (
      <View style={styles.container}>
        <Text style={styles.errorText}>No se pudieron cargar los grupos.</Text>
      </View>
    );
  }

  if (groups.length === 0) {
    return (
      <Pressable
        style={styles.emptyBtn}
        onPress={() => router.push('/(app)/groups')}
        accessibilityRole="button"
        accessibilityLabel="Crear grupo familiar"
      >
        <MaterialCommunityIcons name="account-group-outline" size={18} color={COLORS.primary} />
        <Text style={styles.emptyText}>Crear grupo familiar</Text>
        <MaterialCommunityIcons name="chevron-right" size={18} color={COLORS.primary} />
      </Pressable>
    );
  }

  if (!selectedGroup) {
    return (
      <View style={styles.container}>
        <Text style={styles.label}>Grupo:</Text>
        <View style={styles.chipRow}>
          {groups.map((g) => (
            <Pressable
              key={g.id}
              style={styles.chip}
              onPress={() => selectGroup(g)}
              accessibilityRole="button"
              accessibilityLabel={`Seleccionar grupo ${g.name}`}
            >
              <Text style={styles.chipText} numberOfLines={1}>{g.name}</Text>
            </Pressable>
          ))}
        </View>
      </View>
    );
  }

  return (
    <Pressable
      style={styles.selected}
      onPress={() => router.push('/(app)/groups')}
      accessibilityRole="button"
      accessibilityLabel={`Grupo activo: ${selectedGroup.name}. Tocar para cambiar.`}
    >
      <MaterialCommunityIcons name="account-group" size={16} color={COLORS.primary} />
      <Text style={styles.selectedName} numberOfLines={1}>{selectedGroup.name}</Text>
      <MaterialCommunityIcons name="chevron-down" size={16} color={COLORS.textSecondary} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.sm,
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    backgroundColor: COLORS.surface,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  loadingText: {
    fontSize: FONT.captionSize,
    color: COLORS.textSecondary,
  },
  errorText: {
    fontSize: FONT.captionSize,
    color: COLORS.error,
  },
  label: {
    fontSize: FONT.captionSize,
    color: COLORS.textSecondary,
    fontWeight: '600',
  },
  chipRow: {
    flexDirection: 'row',
    gap: SPACING.xs,
    flexWrap: 'wrap',
  },
  chip: {
    backgroundColor: COLORS.primarySurface,
    borderRadius: RADIUS.full,
    paddingHorizontal: SPACING.sm,
    paddingVertical: 4,
    borderWidth: 1,
    borderColor: COLORS.primaryLight,
    maxWidth: 160,
  },
  chipText: {
    fontSize: FONT.captionSize,
    color: COLORS.primary,
    fontWeight: '600',
  },
  selected: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.xs,
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    backgroundColor: COLORS.surface,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  selectedName: {
    flex: 1,
    fontSize: FONT.captionSize + 1,
    color: COLORS.textPrimary,
    fontWeight: '600',
  },
  emptyBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.xs,
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    backgroundColor: COLORS.primarySurface,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.primaryLight,
    ...SHADOW.sm,
  },
  emptyText: {
    flex: 1,
    fontSize: FONT.captionSize + 1,
    color: COLORS.primary,
    fontWeight: '600',
  },
});

import React, { useState } from 'react';
import {
  Alert,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useRouter } from 'expo-router';
import { useFamilyGroups } from '@/hooks/useFamilyGroups';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { familyGroupsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { LoadingScreen } from '@/components/LoadingScreen';
import { ErrorState } from '@/components/ErrorState';
import { EmptyState } from '@/components/EmptyState';
import { ListItem } from '@/components/ListItem';
import { AppButton } from '@/components/AppButton';
import { AppInput } from '@/components/AppInput';
import { FormError } from '@/components/FormError';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT_SIZE, RADIUS, SPACING } from '@/utils/theme';

export function FamilyGroupsScreen() {
  const { data, loading, error, refresh } = useFamilyGroups();
  const { selectGroup } = useFamilyGroupContext();
  const router = useRouter();

  const [creating, setCreating] = useState(false);
  const [newName, setNewName] = useState('');
  const [createLoading, setCreateLoading] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);

  if (loading && data.length === 0) return <LoadingScreen message="Cargando grupos..." />;
  if (error && data.length === 0) {
    return (
      <View style={styles.fill}>
        <ErrorState
          message={friendlyMessage(error)}
          traceId={error.traceId}
          onRetry={refresh}
        />
      </View>
    );
  }

  async function handleCreate() {
    if (!newName.trim()) {
      setCreateError('El nombre del grupo es obligatorio.');
      return;
    }
    setCreateLoading(true);
    setCreateError(null);
    try {
      await familyGroupsApi.create({ name: newName.trim() });
      setCreating(false);
      setNewName('');
      refresh();
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.normalized.status === 409) {
          setCreateError('Ya pertenecés a un grupo familiar activo.');
        } else {
          setCreateError(friendlyMessage(err.normalized));
        }
      } else {
        setCreateError('Error al crear el grupo.');
      }
      setCreateLoading(false);
    }
  }

  function handleSelect(id: number) {
    const group = data.find((g) => g.id === id);
    if (group) {
      selectGroup(group);
      router.push(`/(app)/groups/${id}`);
    }
  }

  return (
    <ScrollView
      style={styles.scroll}
      contentContainerStyle={styles.content}
      refreshControl={
        <RefreshControl refreshing={loading} onRefresh={refresh} tintColor={COLORS.primary} />
      }
      showsVerticalScrollIndicator={false}
    >
      {data.length === 0 ? (
        <EmptyState
          message="Todavía no pertenecés a un grupo familiar."
          actionTitle="Crear grupo"
          onAction={() => setCreating(true)}
        />
      ) : (
        <>
          {data.map((group) => (
            <ListItem
              key={group.id}
              title={group.name}
              subtitle={group.default_address ?? group.status}
              onPress={() => handleSelect(group.id)}
            />
          ))}
        </>
      )}

      {creating ? (
        <View style={styles.createCard}>
          <Text style={styles.createTitle}>Nuevo grupo familiar</Text>
          <FormError message={createError} />
          <AppInput
            label="Nombre del grupo"
            value={newName}
            onChangeText={(t) => { setNewName(t); setCreateError(null); }}
            placeholder="Ej: Familia García"
            editable={!createLoading}
            returnKeyType="done"
            onSubmitEditing={handleCreate}
          />
          <View style={styles.createActions}>
            <AppButton
              title="Cancelar"
              variant="ghost"
              onPress={() => { setCreating(false); setNewName(''); setCreateError(null); }}
              disabled={createLoading}
              style={styles.createBtn}
            />
            <AppButton
              title="Crear"
              onPress={handleCreate}
              loading={createLoading}
              style={styles.createBtn}
            />
          </View>
        </View>
      ) : data.length > 0 ? null : null}

      {!creating && data.length > 0 ? (
        <AppButton
          title="Crear otro grupo"
          variant="outline"
          onPress={() => setCreating(true)}
          style={styles.createOther}
        />
      ) : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1 },
  scroll: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  content: {
    padding: SPACING.md,
    flexGrow: 1,
    paddingBottom: SPACING.xxl,
  },
  createCard: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginTop: SPACING.md,
  },
  createTitle: {
    fontSize: FONT_SIZE.md,
    fontWeight: '600',
    color: COLORS.textPrimary,
    marginBottom: SPACING.md,
  },
  createActions: {
    flexDirection: 'row',
    gap: SPACING.sm,
    marginTop: SPACING.sm,
  },
  createBtn: {
    flex: 1,
  },
  createOther: {
    marginTop: SPACING.lg,
  },
});

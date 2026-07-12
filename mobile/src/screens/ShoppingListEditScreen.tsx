import React, { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { FormError } from '@/components/FormError';
import { LoadingScreen } from '@/components/LoadingScreen';
import { ErrorState } from '@/components/ErrorState';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useShoppingListDetail } from '@/hooks/useShoppingListDetail';
import { shoppingListsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

const STATUS_OPTIONS = [
  { value: 'draft', label: 'Borrador' },
  { value: 'active', label: 'Activa' },
] as const;

type EditableStatus = typeof STATUS_OPTIONS[number]['value'];

interface Props {
  listId: number;
}

export function ShoppingListEditScreen({ listId }: Props) {
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;
  const { list, loading, error, refresh } = useShoppingListDetail(groupId, listId);

  const [status, setStatus] = useState<EditableStatus | null>(null);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const currentStatus = status ?? (list?.status as EditableStatus | undefined) ?? 'draft';

  async function handleSave() {
    if (!groupId || !list) return;
    setSaving(true);
    setSaveError(null);
    try {
      await shoppingListsApi.update(groupId, listId, { status: currentStatus });
      goBackOrHome();
    } catch (err) {
      if (err instanceof ApiError) setSaveError(err.normalized.message);
      else setSaveError('Error al guardar los cambios.');
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <LoadingScreen message="Cargando lista..." />;

  if (error) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Editar lista" showBack onBack={goBackOrHome} />
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId ?? ''} onRetry={refresh} type="server" />
      </View>
    );
  }

  if (!list || list.status === 'completed') {
    return (
      <View style={styles.fill}>
        <AppHeader title="Editar lista" showBack onBack={goBackOrHome} />
        <View style={styles.centered}>
          <Text style={styles.hint}>Esta lista no puede editarse.</Text>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.fill}>
      <AppHeader title={`Editar Lista #${list.id}`} subtitle={selectedGroup?.name} showBack onBack={goBackOrHome} />
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        <FormError message={saveError} />

        <Text style={styles.label}>Estado</Text>
        <View style={styles.options}>
          {STATUS_OPTIONS.map((opt) => (
            <Pressable
              key={opt.value}
              style={[styles.option, currentStatus === opt.value && styles.optionSelected]}
              onPress={() => setStatus(opt.value)}
              accessibilityRole="radio"
              accessibilityLabel={opt.label}
              accessibilityState={{ selected: currentStatus === opt.value }}
            >
              <Text style={[styles.optionText, currentStatus === opt.value && styles.optionTextSelected]}>
                {opt.label}
              </Text>
            </Pressable>
          ))}
        </View>

        <AppButton title="Guardar cambios" onPress={handleSave} loading={saving} fullWidth />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: SPACING.xl },
  hint: { fontSize: FONT.bodySize, color: COLORS.textSecondary, textAlign: 'center' },
  content: { padding: SPACING.md, gap: SPACING.md },
  label: { fontSize: FONT.labelSize, fontWeight: FONT.labelWeight, color: COLORS.textSecondary },
  options: { gap: SPACING.sm },
  option: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.sm,
    borderWidth: 1,
    borderColor: COLORS.border,
    padding: SPACING.md,
  },
  optionSelected: { borderColor: COLORS.primary, backgroundColor: COLORS.primarySurface },
  optionText: { fontSize: FONT.bodySize, color: COLORS.textPrimary, fontWeight: '500' },
  optionTextSelected: { color: COLORS.primary, fontWeight: '700' },
});

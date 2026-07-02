import React, { useState } from 'react';
import {
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useRouter } from 'expo-router';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { BudgetProgressCard } from '@/components/BudgetProgressCard';
import { FormError } from '@/components/FormError';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { FamilyGroupSelector } from '@/components/FamilyGroupSelector';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useBudgets } from '@/hooks/useBudgets';
import { budgetsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, RADIUS, SPACING, TOUCH_TARGET } from '@/utils/theme';
import type { Budget } from '@/types/budget';

export function BudgetsScreen() {
  const router = useRouter();
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;
  const { data, loading, error, refresh } = useBudgets(groupId);

  const [createVisible, setCreateVisible] = useState(false);
  const [year, setYear] = useState(String(new Date().getFullYear()));
  const [month, setMonth] = useState(String(new Date().getMonth() + 1));
  const [amount, setAmount] = useState('');
  const [creating, setCreating] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);

  async function handleCreate() {
    if (!groupId) return;
    const y = parseInt(year, 10);
    const m = parseInt(month, 10);
    const a = parseFloat(amount);
    if (!y || !m || isNaN(a) || a <= 0) {
      setCreateError('Completá todos los campos correctamente.');
      return;
    }
    setCreating(true);
    setCreateError(null);
    try {
      await budgetsApi.create(groupId, { year: y, month: m, total_amount: a, currency: 'ARS' });
      setCreateVisible(false);
      setAmount('');
      refresh();
    } catch (err) {
      if (err instanceof ApiError) setCreateError(err.normalized.message);
      else setCreateError('Error al crear el presupuesto.');
    } finally {
      setCreating(false);
    }
  }

  const handlePress = (b: Budget) => {
    router.push({ pathname: '/(app)/budgets/[id]' as never, params: { id: String(b.id) } });
  };

  if (!selectedGroup) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Presupuesto" showBack onBack={goBackOrHome} />
        <View style={styles.centered}>
          <FamilyGroupSelector />
          <EmptyState icon="account-group-outline" message="Seleccioná un grupo familiar." />
        </View>
      </View>
    );
  }

  if (loading) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Presupuesto" subtitle={selectedGroup.name} showBack onBack={goBackOrHome} />
        <LoadingScreen message="Cargando presupuestos..." />
      </View>
    );
  }

  return (
    <View style={styles.fill}>
      <AppHeader
        title="Presupuesto"
        subtitle={selectedGroup.name}
        showBack
        onBack={goBackOrHome}
        rightAction={
          <Pressable
            onPress={() => setCreateVisible(true)}
            accessibilityLabel="Nuevo presupuesto"
            accessibilityRole="button"
            style={styles.addBtn}
          >
            <MaterialCommunityIcons name="plus" size={26} color={COLORS.textInverse} />
          </Pressable>
        }
      />

      {error && data.length === 0 ? (
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId ?? ''} onRetry={refresh} type="server" />
      ) : (
        <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
          {data.length === 0 ? (
            <EmptyState icon="clipboard-list-outline" message="No hay presupuestos. Creá uno para empezar." />
          ) : (
            data.map((b) => (
              <Pressable key={b.id} onPress={() => handlePress(b)}>
                <BudgetProgressCard budget={b} />
              </Pressable>
            ))
          )}
        </ScrollView>
      )}

      <Modal visible={createVisible} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setCreateVisible(false)}>
        <View style={styles.modal}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>Nuevo presupuesto</Text>
            <Pressable onPress={() => setCreateVisible(false)} style={styles.modalClose} accessibilityLabel="Cerrar">
              <MaterialCommunityIcons name="close" size={24} color={COLORS.textPrimary} />
            </Pressable>
          </View>
          <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
            <ScrollView contentContainerStyle={styles.modalContent} keyboardShouldPersistTaps="handled">
              <FormError message={createError} />
              <View style={styles.field}>
                <Text style={styles.fieldLabel}>Año</Text>
                <TextInput style={styles.input} value={year} onChangeText={setYear} keyboardType="number-pad" placeholderTextColor={COLORS.textHint} />
              </View>
              <View style={styles.field}>
                <Text style={styles.fieldLabel}>Mes (1-12)</Text>
                <TextInput style={styles.input} value={month} onChangeText={setMonth} keyboardType="number-pad" placeholderTextColor={COLORS.textHint} />
              </View>
              <View style={styles.field}>
                <Text style={styles.fieldLabel}>Monto total (ARS)</Text>
                <TextInput style={styles.input} value={amount} onChangeText={setAmount} keyboardType="decimal-pad" placeholder="0.00" placeholderTextColor={COLORS.textHint} />
              </View>
              <AppButton title="Crear presupuesto" onPress={handleCreate} loading={creating} fullWidth />
            </ScrollView>
          </KeyboardAvoidingView>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  centered: { flex: 1, justifyContent: 'center' },
  content: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
  addBtn: { width: TOUCH_TARGET, height: TOUCH_TARGET, alignItems: 'center', justifyContent: 'center' },
  modal: { flex: 1, backgroundColor: COLORS.background },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: SPACING.md,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
    backgroundColor: COLORS.surface,
  },
  modalTitle: { fontSize: FONT.subtitleSize, fontWeight: FONT.subtitleWeight, color: COLORS.textPrimary },
  modalClose: { padding: SPACING.xs },
  modalContent: { padding: SPACING.md, gap: SPACING.md },
  field: { gap: 6 },
  fieldLabel: { fontSize: FONT.labelSize, fontWeight: FONT.labelWeight, color: COLORS.textSecondary },
  input: {
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.sm,
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    fontSize: FONT.bodySize,
    color: COLORS.textPrimary,
    minHeight: 44,
  },
});

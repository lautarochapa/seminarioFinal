import React, { useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View } from 'react-native';
import { useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { FormError } from '@/components/FormError';
import { EmptyState } from '@/components/EmptyState';
import { FamilyGroupSelector } from '@/components/FamilyGroupSelector';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { shoppingListsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { goBackOrHome } from '@/utils/navigation';
import { COLORS, SPACING } from '@/utils/theme';

export function ShoppingListCreateScreen() {
  const router = useRouter();
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;

  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);

  async function handleCreate() {
    if (!groupId) return;
    setSubmitting(true);
    setSubmitError(null);
    try {
      const res = await shoppingListsApi.create(groupId, { source_type: 'manual', status: 'active' });
      router.replace({ pathname: '/(app)/shopping-lists/[id]' as never, params: { id: String(res.data.id) } });
    } catch (err) {
      if (err instanceof ApiError) {
        setSubmitError(err.normalized.message);
      } else {
        setSubmitError('Error al crear la lista. Intentá de nuevo.');
      }
    } finally {
      setSubmitting(false);
    }
  }

  if (!selectedGroup) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Nueva lista" showBack onBack={goBackOrHome} />
        <View style={styles.centered}>
          <FamilyGroupSelector />
          <EmptyState icon="account-group-outline" message="Seleccioná un grupo familiar primero." />
        </View>
      </View>
    );
  }

  return (
    <View style={styles.fill}>
      <AppHeader title="Nueva lista" subtitle={selectedGroup.name} showBack onBack={goBackOrHome} />
      <KeyboardAvoidingView style={styles.fill} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView
          style={styles.scroll}
          contentContainerStyle={styles.content}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <FormError message={submitError} />
          <AppButton
            title="Crear lista manual"
            onPress={handleCreate}
            loading={submitting}
            fullWidth
          />
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  centered: { flex: 1, justifyContent: 'center' },
  scroll: { flex: 1 },
  content: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
});

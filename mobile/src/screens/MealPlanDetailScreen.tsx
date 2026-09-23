import React, { useEffect, useRef, useState } from 'react';
import { Alert, FlatList, Keyboard, KeyboardAvoidingView, Modal, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { ModalSurface } from '@/components/ModalSurface';
import { parseDateOnly, parseDecimal } from '@/utils/formValues';
import { mealPlanLabel } from '@/utils/mealPlan';
import { useSectionBackNavigation } from '@/hooks/useSectionBackNavigation';
import { useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { MealSlotCard } from '@/components/MealSlotCard';
import { GenerationSummary } from '@/components/GenerationSummary';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useMealPlanDetail } from '@/hooks/useMealPlanDetail';
import { mealPlansApi, mealTypesApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import type { MealType } from '@/types/mealPlan';

type Props = {
  planId: number;
  preselectRecipeId?: number | null;
  preselectRecipeName?: string | null;
};

export function MealPlanDetailScreen({ planId, preselectRecipeId = null, preselectRecipeName = null }: Props) {
  const router = useRouter();
  const goBack = useSectionBackNavigation('/(app)/meal-plans', preselectRecipeId ? { addRecipeId: String(preselectRecipeId), addRecipeName: preselectRecipeName ?? '' } : {});
  const generatingRef = useRef(false);
  const savingRef = useRef(false);
  const modalScroll = useRef<ScrollView>(null);
  const portionsFocused = useRef(false);
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;
  const { data, loading, error, refresh } = useMealPlanDetail(groupId, planId);
  const [generating, setGenerating] = useState(false);
  const [summary, setSummary] = useState<string | null>(null);

  const [mealTypes, setMealTypes] = useState<MealType[]>([]);
  const [addVisible, setAddVisible] = useState(false);
  const [addDate, setAddDate] = useState('');
  const [addMealTypeId, setAddMealTypeId] = useState<number | null>(null);
  const [addRecipeId, setAddRecipeId] = useState('');
  const [addServings, setAddServings] = useState('');
  const [addFree, setAddFree] = useState('');
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  useEffect(() => {
    mealTypesApi.list().then((res) => setMealTypes(res.data)).catch(() => setMealTypes([]));
  }, []);

  useEffect(() => {
    if (!addVisible) return;
    const listener = Keyboard.addListener('keyboardDidShow', () => {
      if (portionsFocused.current) modalScroll.current?.scrollToEnd({ animated: true });
    });
    return () => listener.remove();
  }, [addVisible]);

  function openAdd() {
    portionsFocused.current = false;
    setFormError(null);
    setAddDate(data?.start_date ?? '');
    setAddMealTypeId(mealTypes[0]?.id ?? null);
    setAddRecipeId(preselectRecipeId ? String(preselectRecipeId) : '');
    setAddServings('');
    setAddFree('');
    setAddVisible(true);
  }

  async function handleAdd() {
    if (!groupId || savingRef.current) return;
    const date = addDate.trim();
    const portions = addServings.trim() ? parseDecimal(addServings) : null;
    if (!parseDateOnly(date) || !data || date < data.start_date.slice(0, 10) || date > data.end_date.slice(0, 10)) {
      setFormError('Ingresá una fecha válida dentro de las fechas del plan.'); return;
    }
    if (!addMealTypeId) { setFormError('Seleccioná el tipo de comida.'); return; }
    if (addServings.trim() && (portions === null || portions <= 0 || portions > 999999.99)) {
      setFormError('Ingresá una cantidad de porciones mayor que cero.'); return;
    }
    const hasRecipe = addRecipeId.trim() !== '';
    if (hasRecipe && (!/^\d+$/.test(addRecipeId.trim()) || Number(addRecipeId) <= 0)) {
      setFormError('Seleccioná una receta válida.'); return;
    }
    if (!hasRecipe && addFree.trim() === '') {
      Alert.alert('Falta contenido', 'Elegí una receta o escribí una comida libre.');
      return;
    }
    savingRef.current = true;
    setFormError(null);
    setSaving(true);
    try {
      await mealPlansApi.createItem(groupId, planId, {
        date,
        meal_type_id: addMealTypeId,
        recipe_id: hasRecipe ? Number(addRecipeId) : null,
        free_meal_description: hasRecipe ? null : addFree.trim(),
        servings_total: portions,
      });
      setAddVisible(false);
      refresh();
    } catch (err) {
      Alert.alert('No se pudo agregar', err instanceof ApiError ? friendlyMessage(err.normalized) : 'Intentá nuevamente.');
    } finally {
      savingRef.current = false;
      setSaving(false);
    }
  }

  function closeAdd() {
    if (savingRef.current) return;
    if (Keyboard.isVisible()) { Keyboard.dismiss(); return; }
    const dirty = Boolean(addServings || addFree || addDate !== data?.start_date || addRecipeId !== (preselectRecipeId ? String(preselectRecipeId) : '') || addMealTypeId !== (mealTypes[0]?.id ?? null));
    if (!dirty) { setAddVisible(false); return; }
    Alert.alert('¿Descartar los cambios?', 'Esta comida todavía no se guardó.', [
      { text: 'Seguir editando', style: 'cancel' },
      { text: 'Descartar', style: 'destructive', onPress: () => setAddVisible(false) },
    ]);
  }

  function confirmDelete(itemId: number) {
    if (!groupId) return;
    Alert.alert('Quitar del plan', 'Se elimina esta comida del plan.', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Quitar', style: 'destructive',
        onPress: async () => {
          try {
            await mealPlansApi.deleteItem(groupId, planId, itemId);
            refresh();
          } catch (err) {
            Alert.alert('Error', err instanceof ApiError ? friendlyMessage(err.normalized) : 'No se pudo quitar.');
          }
        },
      },
    ]);
  }

  async function handleGenerateList() {
    if (!groupId || generatingRef.current) return;
    generatingRef.current = true;
    setGenerating(true);
    setSummary(null);
    try {
      const res = await mealPlansApi.generateShoppingList(groupId, planId);
      const listId = res.data.id;
      setSummary('Lista generada desde el plan de comidas.');
      if (listId) {
        router.push({ pathname: '/(app)/shopping-lists/[id]' as never, params: { id: String(listId), returnTo: 'meal-plan', returnId: String(planId), addRecipeId: preselectRecipeId ? String(preselectRecipeId) : '', addRecipeName: preselectRecipeName ?? '' } });
      }
    } catch (err) {
      const msg = err instanceof ApiError ? err.normalized.message : 'No se pudo generar la lista.';
      Alert.alert('Error', msg);
    } finally {
      generatingRef.current = false;
      setGenerating(false);
    }
  }

  if (loading) return <LoadingScreen message="Cargando plan..." />;
  if (error) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Plan de comidas" showBack onBack={goBack} />
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" />
      </View>
    );
  }

  return (
    <View style={styles.fill}>
      <AppHeader title="Plan de comidas" showBack onBack={goBack} />
      {data ? (
        <FlatList
          data={data.items ?? []}
          keyExtractor={(item) => String(item.id)}
          ListHeaderComponent={
            <View style={styles.headerCard}>
              <Text style={styles.title}>{data.start_date} / {data.end_date}</Text>
              <Text style={styles.meta}>{mealPlanLabel(data.period_type)} · {mealPlanLabel(data.mode)} · {mealPlanLabel(data.status)}</Text>
              {preselectRecipeName ? <Text style={styles.meta}>Agregando: {preselectRecipeName}</Text> : null}
              <AppButton title="Agregar comida" onPress={openAdd} fullWidth />
              <AppButton title={generating ? 'Generando...' : 'Generar lista'} variant="outline" onPress={handleGenerateList} loading={generating} fullWidth />
              {summary ? <GenerationSummary message={summary} /> : null}
            </View>
          }
          renderItem={({ item }) => (
            <View style={styles.slotRow}>
              <View style={styles.slotCard}>
                <MealSlotCard entry={item} onPress={item.recipe_id ? () => router.push({ pathname: '/(app)/recipes/[id]' as never, params: { id: String(item.recipe_id), returnTo: 'meal-plan', returnId: String(planId) } }) : undefined} />
              </View>
              <AppButton title="Quitar" variant="ghost" onPress={() => confirmDelete(item.id)} />
            </View>
          )}
          contentContainerStyle={styles.list}
          onRefresh={refresh}
          refreshing={loading}
          ListEmptyComponent={<EmptyState icon="clipboard-list-outline" message="Sin entradas en este plan." />}
        />
      ) : <EmptyState icon="clipboard-list-outline" message="No se encontró el plan de comidas." />}

      <Modal visible={addVisible} transparent animationType="fade" onRequestClose={closeAdd} statusBarTranslucent>
        <ModalSurface style={styles.backdrop}>
          <KeyboardAvoidingView testID="meal-plan-keyboard" behavior="padding" style={{ flex: 1 }}>
          <ScrollView ref={modalScroll} testID="meal-plan-form" keyboardShouldPersistTaps="handled" contentContainerStyle={styles.modalScroll}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Agregar comida</Text>
            <Text style={styles.meta}>Fecha · {data?.start_date} / {data?.end_date}</Text>
            <TextInput style={styles.input} value={addDate} onChangeText={setAddDate} placeholder="Fecha (YYYY-MM-DD)" placeholderTextColor={COLORS.textHint} autoCapitalize="none" accessibilityLabel="Fecha" editable={!saving} />
            <View style={styles.chips}>
              {mealTypes.map((mt) => (
                <Pressable key={mt.id} accessibilityRole="button" onPress={() => setAddMealTypeId(mt.id)} style={[styles.chip, addMealTypeId === mt.id && styles.chipOn]}>
                  <Text style={[styles.chipText, addMealTypeId === mt.id && styles.chipTextOn]}>{mt.name}</Text>
                </Pressable>
              ))}
            </View>
            {preselectRecipeName ? <Text style={styles.meta}>Receta: {preselectRecipeName}</Text> : <TextInput style={styles.input} value={addRecipeId} onChangeText={setAddRecipeId} placeholder="ID de receta (opcional)" placeholderTextColor={COLORS.textHint} keyboardType="number-pad" accessibilityLabel="ID de receta" />}
            <TextInput style={styles.input} value={addFree} onChangeText={setAddFree} placeholder="o comida libre" placeholderTextColor={COLORS.textHint} accessibilityLabel="Comida libre" />
            <Text style={styles.meta}>Porciones (opcional)</Text>
            <TextInput style={styles.input} value={addServings} onChangeText={setAddServings} placeholder="Ej.: 1,5" placeholderTextColor={COLORS.textHint} keyboardType="decimal-pad" accessibilityLabel="Porciones" returnKeyType="done" onSubmitEditing={() => Keyboard.dismiss()} onFocus={() => { portionsFocused.current = true; modalScroll.current?.scrollToEnd({ animated: true }); }} onBlur={() => { portionsFocused.current = false; }} editable={!saving} />
            {formError ? <Text accessibilityRole="alert" style={{ color: COLORS.error }}>{formError}</Text> : null}
            <View style={styles.modalActions}>
              <Pressable accessibilityRole="button" onPress={closeAdd} disabled={saving} style={styles.cancelBtn}><Text style={styles.cancelText}>Cancelar</Text></Pressable>
              <AppButton title="Guardar" onPress={() => { void handleAdd(); }} loading={saving} disabled={!addDate.trim() || !addMealTypeId} />
            </View>
          </View>
          </ScrollView>
          </KeyboardAvoidingView>
        </ModalSurface>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
  headerCard: { backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.md, gap: SPACING.md, ...SHADOW.sm },
  title: { fontSize: FONT.titleSize, fontWeight: '800', color: COLORS.textPrimary },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
  slotRow: { gap: SPACING.xs },
  slotCard: {},
  backdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.4)' },
  modalScroll: { flexGrow: 1, justifyContent: 'center', padding: SPACING.md },
  modalCard: { backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.lg, gap: SPACING.sm },
  modalTitle: { fontSize: FONT.titleSize, fontWeight: '800', color: COLORS.textPrimary },
  input: { borderWidth: 1, borderColor: COLORS.border, borderRadius: RADIUS.sm, padding: SPACING.sm, color: COLORS.textPrimary },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: SPACING.xs },
  chip: { borderWidth: 1, borderColor: COLORS.border, borderRadius: RADIUS.sm, paddingVertical: 6, paddingHorizontal: 10 },
  chipOn: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  chipText: { color: COLORS.textPrimary, fontSize: FONT.captionSize },
  chipTextOn: { color: '#fff', fontWeight: '700' },
  modalActions: { flexDirection: 'row', justifyContent: 'flex-end', alignItems: 'center', gap: SPACING.md, marginTop: SPACING.xs },
  cancelBtn: { paddingVertical: SPACING.sm, paddingHorizontal: SPACING.md },
  cancelText: { color: COLORS.textSecondary, fontWeight: '600' },
});

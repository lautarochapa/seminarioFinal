import React, { useEffect, useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { AppInput } from '@/components/AppInput';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { useProfile } from '@/hooks/useProfile';
import { objectivesApi, profileApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { offlineCache } from '@/storage/offlineCache';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT_SIZE, RADIUS, SPACING } from '@/utils/theme';

type Objective = { id: number; code: string; name: string; description?: string | null };
export function GoalsScreen() {
  const { data, loading, error, refresh } = useProfile();
  const [catalog, setCatalog] = useState<Objective[]>([]);
  const [form, setForm] = useState({ height: '', currentWeight: '', targetWeight: '', meals: '', activity: '', objectiveIds: [] as number[] });
  const [initialized, setInitialized] = useState(false);
  const [saving, setSaving] = useState(false); const [message, setMessage] = useState<string | null>(null); const [saveError, setSaveError] = useState<string | null>(null);

  useEffect(() => { objectivesApi.catalog().then((res) => setCatalog(res.data)).catch(() => setCatalog([])); }, []);
  useEffect(() => {
    if (!data || initialized) return;
    const timer = setTimeout(() => { setForm({ height: data.height_cm?.toString() ?? '', currentWeight: data.current_weight_kg?.toString() ?? '', targetWeight: data.target_weight_kg?.toString() ?? '', meals: data.meals_per_day?.toString() ?? '', activity: data.activity_level ?? '', objectiveIds: data.objectives.map((o) => o.id) }); setInitialized(true); }, 0);
    return () => clearTimeout(timer);
  }, [data, initialized]);

  const positive = (value: string) => value === '' || (Number.isFinite(Number(value)) && Number(value) > 0);
  async function save() {
    setMessage(null); setSaveError(null);
    if (!positive(form.currentWeight)) return setSaveError('Ingresá un peso válido.');
    if (!positive(form.height)) return setSaveError('La altura debe ser mayor a cero.');
    if (!positive(form.targetWeight)) return setSaveError('Ingresá un peso objetivo válido.');
    if (form.objectiveIds.length === 0) return setSaveError('Seleccioná un objetivo.');
    setSaving(true);
    try {
      await profileApi.update({ height_cm: form.height ? Number(form.height) : null, current_weight_kg: form.currentWeight ? Number(form.currentWeight) : null, target_weight_kg: form.targetWeight ? Number(form.targetWeight) : null, meals_per_day: form.meals ? Number(form.meals) : null, activity_level: form.activity || null, objective_ids: form.objectiveIds });
      await offlineCache.clearAll(); setMessage('Tus objetivos se guardaron correctamente.'); refresh();
    } catch (err) { setSaveError(err instanceof ApiError ? friendlyMessage(err.normalized) : 'No pudimos guardar tus objetivos.'); }
    finally { setSaving(false); }
  }

  if (loading && !data) return <LoadingScreen message="Cargando tus objetivos..." />;
  if (error && !data) return <View style={styles.fill}><AppHeader title="Mis objetivos" showBack onBack={goBackOrHome} /><ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" /></View>;
  return <KeyboardAvoidingView style={styles.fill} behavior={Platform.OS === 'ios' ? 'padding' : undefined}><AppHeader title="Mis objetivos" showBack onBack={goBackOrHome} /><ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
    <Block title="Datos actuales"><AppInput label="Peso actual (kg)" value={form.currentWeight} onChangeText={(currentWeight) => setForm((v) => ({ ...v, currentWeight }))} keyboardType="decimal-pad" /><AppInput label="Altura (cm)" value={form.height} onChangeText={(height) => setForm((v) => ({ ...v, height }))} keyboardType="decimal-pad" /></Block>
    <Block title="Objetivo"><AppInput label="Peso objetivo (kg)" value={form.targetWeight} onChangeText={(targetWeight) => setForm((v) => ({ ...v, targetWeight }))} keyboardType="decimal-pad" /><View style={styles.chips}>{catalog.map((item) => { const active = form.objectiveIds.includes(item.id); return <Pressable key={item.id} accessibilityRole="checkbox" accessibilityState={{ checked: active }} style={[styles.chip, active && styles.active]} onPress={() => setForm((v) => ({ ...v, objectiveIds: active ? v.objectiveIds.filter((id) => id !== item.id) : [...v.objectiveIds, item.id] }))}><Text style={[styles.chipText, active && styles.activeText]}>{item.name}</Text></Pressable>; })}</View></Block>
    <Block title="Rutina"><Text style={styles.label}>Nivel de actividad</Text><View style={styles.chips}>{[['sedentary','Sedentario'],['light','Ligero'],['moderate','Moderado'],['active','Activo'],['very_active','Muy activo']].map(([value,label]) => <Pressable key={value} style={[styles.chip, form.activity === value && styles.active]} onPress={() => setForm((v) => ({ ...v, activity: value }))}><Text style={[styles.chipText, form.activity === value && styles.activeText]}>{label}</Text></Pressable>)}</View></Block>
    <Block title="Cocina habitual"><AppInput label="Cantidad habitual de personas" value={form.meals} onChangeText={(meals) => setForm((v) => ({ ...v, meals }))} keyboardType="number-pad" /></Block>
    {saveError ? <Text style={styles.error} accessibilityLiveRegion="polite">{saveError}</Text> : null}{message ? <Text style={styles.success} accessibilityLiveRegion="polite">{message}</Text> : null}<AppButton title="Guardar" onPress={save} loading={saving} fullWidth />
  </ScrollView></KeyboardAvoidingView>;
}
function Block({ title, children }: { title: string; children: React.ReactNode }) { return <View style={styles.block}><Text style={styles.title}>{title}</Text>{children}</View>; }
const styles = StyleSheet.create({ fill: { flex: 1, backgroundColor: COLORS.background }, content: { padding: SPACING.md, paddingBottom: SPACING.xxl, gap: SPACING.md }, block: { backgroundColor: COLORS.surface, padding: SPACING.md, borderRadius: RADIUS.md, borderWidth: 1, borderColor: COLORS.border }, title: { fontSize: FONT_SIZE.lg, fontWeight: '800', color: COLORS.textPrimary, marginBottom: SPACING.md }, label: { color: COLORS.textSecondary, fontWeight: '600', marginBottom: SPACING.sm }, chips: { flexDirection: 'row', flexWrap: 'wrap', gap: SPACING.sm }, chip: { minHeight: 44, justifyContent: 'center', borderWidth: 1, borderColor: COLORS.border, borderRadius: RADIUS.full, paddingHorizontal: SPACING.md }, active: { borderColor: COLORS.primary, backgroundColor: COLORS.primarySurface }, chipText: { color: COLORS.textPrimary }, activeText: { color: COLORS.primary, fontWeight: '700' }, error: { color: COLORS.error, fontWeight: '700' }, success: { color: COLORS.success, fontWeight: '700' } });

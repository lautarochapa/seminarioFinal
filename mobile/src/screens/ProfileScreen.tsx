import React, { useState } from 'react';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useProfile } from '@/hooks/useProfile';
import { useAuth } from '@/auth/AuthContext';
import { LoadingScreen } from '@/components/LoadingScreen';
import { ErrorState } from '@/components/ErrorState';
import { ScreenContainer } from '@/components/ScreenContainer';
import { SectionHeader } from '@/components/SectionHeader';
import { AppButton } from '@/components/AppButton';
import { AppInput } from '@/components/AppInput';
import { FormError } from '@/components/FormError';
import { profileApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, FONT_SIZE, RADIUS, SPACING } from '@/utils/theme';

function Row({ label, value }: { label: string; value: string | number | null | undefined }) {
  if (value === null || value === undefined || value === '') return null;
  return (
    <View style={styles.row}>
      <Text style={styles.rowLabel}>{label}</Text>
      <Text style={styles.rowValue}>{String(value)}</Text>
    </View>
  );
}

const ACTIVITY_LABELS: Record<string, string> = {
  sedentary: 'Sedentario',
  light: 'Ligero',
  moderate: 'Moderado',
  active: 'Activo',
  very_active: 'Muy activo',
};

const GENDER_LABELS: Record<string, string> = {
  male: 'Masculino',
  female: 'Femenino',
  other: 'Otro',
};

export function ProfileScreen() {
  const router = useRouter();
  const { data, loading, error, refresh } = useProfile();
  const { logout, isLoading: logoutLoading, refreshCurrentUser } = useAuth();
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [feedback, setFeedback] = useState<string | null>(null);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [form, setForm] = useState({ name: '', lastname: '', phone: '', notes: '' });

  async function saveProfile() {
    setSaving(true); setSubmitError(null); setFeedback(null); setFieldErrors({});
    try {
      await profileApi.update({ name: form.name.trim(), lastname: form.lastname.trim(), phone: form.phone.trim() || null, notes: form.notes.trim() || null });
      await refreshCurrentUser(); setFeedback('Perfil actualizado correctamente.'); setEditing(false); refresh();
    } catch (err) {
      if (err instanceof ApiError) { setFieldErrors(err.normalized.fieldErrors); setSubmitError(friendlyMessage(err.normalized)); }
      else setSubmitError('No pudimos guardar los cambios. Intentá nuevamente.');
    } finally { setSaving(false); }
  }

  function confirmLogout() {
    Alert.alert(
      'Cerrar sesión',
      '¿Querés cerrar la sesión actual?',
      [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Cerrar sesión', style: 'destructive', onPress: logout },
      ],
      { cancelable: true },
    );
  }

  if (loading && !data) return <LoadingScreen message="Cargando perfil..." />;
  if (error && !data) {
    return (
      <ScreenContainer>
        <ErrorState
          message={friendlyMessage(error)}
          traceId={error.traceId}
          onRetry={refresh}
          type="server"
        />
      </ScreenContainer>
    );
  }
  if (!data) return null;

  return (
    <ScrollView
      style={styles.scroll}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={loading} onRefresh={refresh} tintColor={COLORS.primary} />}
      showsVerticalScrollIndicator={false}
    >
      {/* Avatar header */}
      <View style={styles.avatarSection}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>
            {data.name.charAt(0).toUpperCase()}{data.lastname.charAt(0).toUpperCase()}
          </Text>
        </View>
        <Text style={styles.name}>{data.name} {data.lastname}</Text>
        <Text style={styles.email}>{data.email}</Text>
      </View>

      <SectionHeader title="Datos personales" />
      <View style={styles.card}>
        {feedback ? <Text style={styles.success} accessibilityLiveRegion="polite">{feedback}</Text> : null}
        <FormError message={submitError} />
        {editing ? <>
          <AppInput label="Nombre" value={form.name} onChangeText={(name) => setForm((v) => ({ ...v, name }))} error={fieldErrors.name?.[0]} editable={!saving} />
          <AppInput label="Apellido" value={form.lastname} onChangeText={(lastname) => setForm((v) => ({ ...v, lastname }))} error={fieldErrors.lastname?.[0]} editable={!saving} />
          <AppInput label="Email" value={data.email} editable={false} hint="El email no se modifica desde este formulario." />
          <AppInput label="Teléfono" value={form.phone} onChangeText={(phone) => setForm((v) => ({ ...v, phone }))} keyboardType="phone-pad" error={fieldErrors.phone?.[0]} editable={!saving} />
          <AppInput label="Notas" value={form.notes} onChangeText={(notes) => setForm((v) => ({ ...v, notes }))} multiline error={fieldErrors.notes?.[0]} editable={!saving} />
          <View style={styles.editActions}><AppButton title="Cancelar" variant="ghost" onPress={() => setEditing(false)} disabled={saving} style={styles.editButton} /><AppButton title="Guardar" onPress={saveProfile} loading={saving} style={styles.editButton} /></View>
        </> : null}
        {!editing ? <>
        <Row label="Teléfono" value={data.phone} />
        <Row label="Fecha de nacimiento" value={data.birth_date} />
        <Row label="Género" value={data.gender ? GENDER_LABELS[data.gender] ?? data.gender : null} />
        <Row label="Altura" value={data.height_cm ? `${data.height_cm} cm` : null} />
        <Row label="Peso actual" value={data.current_weight_kg ? `${data.current_weight_kg} kg` : null} />
        <Row label="Peso objetivo" value={data.target_weight_kg ? `${data.target_weight_kg} kg` : null} />
        <Row label="Actividad" value={data.activity_level ? ACTIVITY_LABELS[data.activity_level] ?? data.activity_level : null} />
        <Row label="Comidas por día" value={data.meals_per_day} />
        <AppButton title="Editar datos" variant="outline" onPress={() => { setFeedback(null); setForm({ name: data.name, lastname: data.lastname, phone: data.phone ?? '', notes: data.notes ?? '' }); setEditing(true); }} fullWidth />
        </> : null}
      </View>

      <SectionHeader title="Preferencias" />
      <View style={styles.card}>
        <Row label="Salud" value={data.preferences.uses_app_for_health ? 'Sí' : 'No'} />
        <Row label="Presupuesto" value={data.preferences.uses_app_for_budget ? 'Sí' : 'No'} />
        <Row label="Organización" value={data.preferences.uses_app_for_organization ? 'Sí' : 'No'} />
      </View>

      {data.notes ? (
        <>
          <SectionHeader title="Notas" />
          <View style={styles.card}>
            <Text style={styles.notes}>{data.notes}</Text>
          </View>
        </>
      ) : null}

      {/* Settings and logout at bottom */}
      <View style={styles.logoutSection}>
        <AppButton
          title="Ajustes"
          variant="outline"
          onPress={() => router.push('/(app)/settings' as never)}
          fullWidth
          style={styles.settingsBtn}
        />
        <AppButton
          title="Cerrar sesión"
          variant="danger"
          onPress={confirmLogout}
          loading={logoutLoading}
          fullWidth
        />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  content: {
    padding: SPACING.md,
    paddingBottom: SPACING.xxl,
  },
  avatarSection: {
    alignItems: 'center',
    paddingVertical: SPACING.lg,
    gap: SPACING.xs,
  },
  avatar: {
    width: 88,
    height: 88,
    borderRadius: RADIUS.full,
    backgroundColor: COLORS.primary,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: SPACING.sm,
  },
  avatarText: {
    fontSize: FONT_SIZE.xxl,
    fontWeight: '700',
    color: '#fff',
  },
  name: {
    fontSize: FONT.titleSize,
    fontWeight: FONT.titleWeight,
    color: COLORS.textPrimary,
    textAlign: 'center',
  },
  email: {
    fontSize: FONT_SIZE.sm,
    color: COLORS.textSecondary,
    textAlign: 'center',
  },
  card: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginBottom: SPACING.md,
    gap: SPACING.sm,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 2,
  },
  rowLabel: {
    fontSize: FONT_SIZE.sm,
    color: COLORS.textSecondary,
    flex: 1,
  },
  rowValue: {
    fontSize: FONT_SIZE.sm,
    color: COLORS.textPrimary,
    fontWeight: '500',
    textAlign: 'right',
    flex: 1,
  },
  notes: {
    fontSize: FONT_SIZE.sm,
    color: COLORS.textPrimary,
    lineHeight: 20,
  },
  success: { color: COLORS.success, fontSize: FONT_SIZE.sm, fontWeight: '700' },
  editActions: { flexDirection: 'row', gap: SPACING.sm },
  editButton: { flex: 1 },
  logoutSection: {
    marginTop: SPACING.lg,
    paddingTop: SPACING.lg,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
    gap: SPACING.sm,
  },
  settingsBtn: {
    marginBottom: 0,
  },
});

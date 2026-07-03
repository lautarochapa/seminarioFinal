import React, { useState } from 'react';
import { Alert, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import Constants from 'expo-constants';
import { AppButton } from '@/components/AppButton';
import { AppHeader } from '@/components/AppHeader';
import { SectionHeader } from '@/components/SectionHeader';
import { useAuth } from '@/auth/AuthContext';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useNotificationPreferences } from '@/hooks/useNotificationPreferences';
import { ENV } from '@/config/env';
import { goBackOrHome } from '@/utils/navigation';
import { COLORS, FONT, FONT_SIZE, RADIUS, SPACING } from '@/utils/theme';

export function SettingsScreen() {
  const router = useRouter();
  const { user, logout, isLoading: logoutLoading } = useAuth();
  const { selectedGroup } = useFamilyGroupContext();
  const prefs = useNotificationPreferences();
  const [showDiagnostics, setShowDiagnostics] = useState(false);

  const appVersion = Constants.expoConfig?.version ?? '—';

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

  return (
    <View style={styles.fill}>
      <AppHeader title="Ajustes" showBack onBack={goBackOrHome} />
      <ScrollView contentContainerStyle={styles.content}>
        <SectionHeader title="Grupo familiar activo" />
        <View style={styles.card}>
          <Text style={styles.value}>{selectedGroup ? selectedGroup.name : 'Ningún grupo seleccionado'}</Text>
          <AppButton
            title={selectedGroup ? 'Cambiar grupo' : 'Seleccionar grupo'}
            variant="outline"
            onPress={() => router.push('/(app)/groups' as never)}
          />
        </View>

        <SectionHeader title="Preferencias de notificaciones" />
        <View style={styles.card}>
          {prefs.data.length === 0 ? (
            <Text style={styles.hint}>No hay preferencias configurables.</Text>
          ) : (
            prefs.data.map((item) => (
              <View key={item.notification_type} style={styles.row}>
                <Text style={styles.rowLabel}>{item.notification_type}</Text>
                <Switch
                  value={item.app_enabled}
                  onValueChange={(value) => {
                    const next = prefs.data.map((pref) =>
                      pref.notification_type === item.notification_type ? { ...pref, app_enabled: value } : pref,
                    );
                    prefs.save(next);
                  }}
                  accessibilityLabel={`Notificaciones de ${item.notification_type}`}
                />
              </View>
            ))
          )}
          {prefs.saveError ? <Text style={styles.error}>{prefs.saveError}</Text> : null}
        </View>

        <SectionHeader title="Información" />
        <View style={styles.card}>
          <View style={styles.row}>
            <Text style={styles.rowLabel}>Versión</Text>
            <Text style={styles.rowValue}>{appVersion}</Text>
          </View>
          <View style={styles.row}>
            <Text style={styles.rowLabel}>Usuario</Text>
            <Text style={styles.rowValue}>{user?.email ?? '—'}</Text>
          </View>
        </View>

        {__DEV__ ? (
          <>
            <SectionHeader title="Ambiente (desarrollo)" />
            <View style={styles.card}>
              <View style={styles.row}>
                <Text style={styles.rowLabel}>API URL</Text>
                <Text selectable style={styles.rowValue}>{ENV.API_URL}</Text>
              </View>
              <AppButton
                title={showDiagnostics ? 'Ocultar diagnóstico' : 'Ver diagnóstico'}
                variant="outline"
                onPress={() => setShowDiagnostics((v) => !v)}
              />
              {showDiagnostics ? (
                <Text selectable style={styles.diagnostics}>
                  {JSON.stringify(
                    {
                      appVersion,
                      apiUrl: ENV.API_URL,
                      userId: user?.id ?? null,
                      groupId: selectedGroup?.id ?? null,
                    },
                    null,
                    2,
                  )}
                </Text>
              ) : null}
            </View>
          </>
        ) : null}

        <SectionHeader title="Soporte y legales" />
        <View style={styles.card}>
          <Text style={styles.hint}>Para soporte, contactá al administrador de tu grupo familiar o institución.</Text>
          <Text style={styles.hint}>Política de privacidad: pendiente de publicación.</Text>
        </View>

        <View style={styles.logoutSection}>
          <AppButton title="Cerrar sesión" variant="danger" onPress={confirmLogout} loading={logoutLoading} fullWidth />
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  content: { padding: SPACING.md, paddingBottom: SPACING.xxl, gap: 0 },
  card: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginBottom: SPACING.md,
    gap: SPACING.sm,
  },
  value: { fontSize: FONT.bodySize, fontWeight: '700', color: COLORS.textPrimary },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingVertical: 2 },
  rowLabel: { fontSize: FONT_SIZE.sm, color: COLORS.textSecondary, flex: 1 },
  rowValue: { fontSize: FONT_SIZE.sm, color: COLORS.textPrimary, fontWeight: '500', textAlign: 'right', flex: 1 },
  hint: { fontSize: FONT_SIZE.sm, color: COLORS.textSecondary },
  error: { color: COLORS.error, fontSize: FONT.captionSize, fontWeight: '700' },
  diagnostics: { fontFamily: 'monospace', fontSize: FONT_SIZE.xs, color: COLORS.textSecondary, backgroundColor: COLORS.surfaceElevated, padding: SPACING.sm, borderRadius: RADIUS.sm },
  logoutSection: { marginTop: SPACING.lg, paddingTop: SPACING.lg, borderTopWidth: 1, borderTopColor: COLORS.border },
});

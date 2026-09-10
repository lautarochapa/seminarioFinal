import React, { useCallback, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { onboardingApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT_SIZE, RADIUS, SPACING } from '@/utils/theme';
import type { OnboardingStatus, OnboardingStepKey } from '@/types/onboarding';

type StepConfig = { key: OnboardingStepKey; title: string; hint: string; route: string; cta: string };

const STEPS: StepConfig[] = [
  { key: 'basic_profile', title: '1. Datos básicos', hint: 'Altura, peso y peso objetivo (opcional).', route: '/(app)/goals', cta: 'Completar datos' },
  { key: 'objective', title: '2. Objetivo', hint: 'Elegí al menos un objetivo nutricional.', route: '/(app)/goals', cta: 'Elegir objetivo' },
  { key: 'meals_per_day', title: '3. Comidas por día', hint: 'Cuántas comidas hacés por día.', route: '/(app)/goals', cta: 'Definir comidas' },
  { key: 'food_preferences', title: 'Preferencias alimentarias (opcional)', hint: 'Restricciones, alergias y condiciones.', route: '/(app)/dietary-preferences', cta: 'Revisar preferencias' },
  { key: 'family_group', title: '4. Grupo familiar', hint: 'Creá un grupo o unite a uno existente.', route: '/(app)/groups', cta: 'Ir a grupo familiar' },
];

function stepDetail(key: OnboardingStepKey, status: OnboardingStatus): string {
  const step = status.steps[key];
  if (key === 'basic_profile' && step.missing && step.missing.length) return `Falta: ${step.missing.join(', ')}`;
  if (key === 'objective') return `${step.objectives_count ?? 0} objetivo(s) elegido(s)`;
  if (key === 'meals_per_day') return step.value ? `${step.value} comidas por día` : 'Sin definir';
  if (key === 'food_preferences') return `${step.restrictions_count ?? 0} restricciones · ${step.allergies_count ?? 0} alergias · ${step.health_conditions_count ?? 0} condiciones`;
  if (key === 'family_group') return `${step.groups_count ?? 0} grupo(s) activo(s)`;
  return '';
}

export function OnboardingScreen() {
  const router = useRouter();
  const [status, setStatus] = useState<OnboardingStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await onboardingApi.status();
      setStatus(response.data);
    } catch (err) {
      setError(err instanceof ApiError ? friendlyMessage(err.normalized) : 'No pudimos cargar tu progreso.');
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      const timer = setTimeout(() => { void load(); }, 0);
      return () => clearTimeout(timer);
    }, [load]),
  );

  if (loading && !status) {
    return <LoadingScreen message="Cargando tu progreso" />;
  }

  if (error && !status) {
    return (
      <View style={styles.container}>
        <AppHeader title="Puesta en marcha" />
        <ErrorState message={error} onRetry={load} />
      </View>
    );
  }

  const s = status as OnboardingStatus;
  const total = s.required_steps.length;

  return (
    <View style={styles.container}>
      <AppHeader title="Puesta en marcha" />
      <ScrollView contentContainerStyle={styles.content}>
        <Text style={styles.progress}>
          {s.complete
            ? 'Configuración completa. Ya podés usar stock y recetas.'
            : `Paso ${s.completed_count + 1} de ${total}`}
        </Text>

        {STEPS.map((step) => {
          const data = s.steps[step.key];
          if (!data) return null;
          return (
            <View key={step.key} style={styles.card} testID={`onboarding-step-${step.key}`}>
              <View style={styles.cardTop}>
                <Text style={styles.cardTitle}>{step.title}</Text>
                <Text style={[styles.badge, data.optional ? styles.badgeOptional : data.complete ? styles.badgeOk : styles.badgePending]}>
                  {data.optional ? 'Opcional' : data.complete ? 'Listo' : 'Pendiente'}
                </Text>
              </View>
              <Text style={styles.hint}>{step.hint}</Text>
              <Text style={styles.detail}>{stepDetail(step.key, s)}</Text>
              <AppButton
                title={step.cta}
                variant="outline"
                onPress={() => router.push(step.route as never)}
              />
            </View>
          );
        })}

        {s.complete ? (
          <AppButton title="Ir al inicio" onPress={() => router.replace('/(app)' as never)} fullWidth />
        ) : (
          <AppButton title="Actualizar progreso" variant="ghost" onPress={load} fullWidth />
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  content: { padding: SPACING.lg, gap: SPACING.md },
  progress: { color: COLORS.textPrimary, fontWeight: '800', fontSize: FONT_SIZE.md },
  card: { backgroundColor: COLORS.surface, borderRadius: RADIUS.md, borderWidth: 1, borderColor: COLORS.border, padding: SPACING.md, gap: SPACING.xs },
  cardTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  cardTitle: { color: COLORS.textPrimary, fontWeight: '700', flexShrink: 1, paddingRight: SPACING.sm },
  hint: { color: COLORS.textHint, fontSize: FONT_SIZE.sm },
  detail: { color: COLORS.textHint, fontSize: FONT_SIZE.xs, marginBottom: SPACING.xs },
  badge: { fontSize: FONT_SIZE.xs, fontWeight: '800', overflow: 'hidden' },
  badgeOk: { color: COLORS.success },
  badgePending: { color: COLORS.error },
  badgeOptional: { color: COLORS.textHint },
});

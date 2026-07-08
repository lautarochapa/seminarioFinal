import React, { useState } from 'react';
import { StyleSheet, Text, View, ScrollView } from 'react-native';
import { useRouter } from 'expo-router';
import { AppButton } from '@/components/AppButton';
import { AppInput } from '@/components/AppInput';
import { FormError } from '@/components/FormError';
import { AppLogo } from '@/components/AppLogo';
import { AuthFooterLink } from '@/components/AuthFooterLink';
import { authApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { validateForgotPasswordForm } from '@/validation/passwordResetSchema';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

const NEUTRAL_MESSAGE = 'Si el correo está registrado, vas a recibir instrucciones para restablecer tu contraseña.';

function forgotPasswordErrorMessage(err: ApiError): string {
  switch (err.normalized.status) {
    case 422: return err.normalized.message;
    case 429: return `Demasiados intentos. Esperá${err.normalized.retryAfter ? ` ${err.normalized.retryAfter}s` : ' un momento'} e intentá de nuevo.`;
    case 500: return 'Error del servidor. Intentá más tarde.';
    case 0:
      if (err.normalized.isTimeoutError) return 'La solicitud tardó demasiado. Verificá tu conexión.';
      return 'No se pudo conectar al servidor. Verificá tu conexión.';
    default: return err.normalized.message;
  }
}

export function ForgotPasswordScreen() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [fieldError, setFieldError] = useState<string | undefined>(undefined);
  const [generalError, setGeneralError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [sent, setSent] = useState(false);

  async function handleSubmit() {
    if (submitting) return;
    setGeneralError(null);
    setFieldError(undefined);

    const errors = validateForgotPasswordForm({ email: email.trim() });
    if (Object.keys(errors).length > 0) {
      setFieldError(errors.email);
      return;
    }

    setSubmitting(true);
    try {
      await authApi.forgotPassword({ email: email.trim() });
      // Respuesta siempre neutral, sin revelar si el email existe.
      setSent(true);
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.normalized.status === 422 && err.normalized.fieldErrors.email) {
          setFieldError(err.normalized.fieldErrors.email[0]);
        } else {
          setGeneralError(forgotPasswordErrorMessage(err));
        }
      } else {
        setGeneralError('Ocurrió un error inesperado.');
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <ScrollView
      contentContainerStyle={styles.scroll}
      keyboardShouldPersistTaps="handled"
      showsVerticalScrollIndicator={false}
    >
      <View style={styles.header}>
        <AppLogo variant="large" inverted />
      </View>

      <View style={styles.card}>
        <Text style={styles.cardTitle}>Olvidé mi contraseña</Text>

        {sent ? (
          <Text style={styles.neutralMessage} accessibilityLiveRegion="polite">
            {NEUTRAL_MESSAGE}
          </Text>
        ) : (
          <>
            <Text style={styles.hint}>
              Ingresá tu email y te enviaremos instrucciones para restablecer tu contraseña.
            </Text>

            <FormError message={generalError} />

            <AppInput
              label="Email"
              value={email}
              onChangeText={(t) => { setEmail(t); setFieldError(undefined); }}
              error={fieldError}
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
              returnKeyType="done"
              onSubmitEditing={handleSubmit}
              editable={!submitting}
            />

            <AppButton
              title={submitting ? 'Enviando...' : 'Enviar instrucciones'}
              onPress={handleSubmit}
              loading={submitting}
              disabled={submitting}
              fullWidth
              style={styles.button}
            />
          </>
        )}
      </View>

      <AuthFooterLink
        prompt="¿Ya tenés el código?"
        actionLabel="Volver al login"
        onPress={() => router.replace('/(auth)/login' as never)}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: {
    flexGrow: 1,
    backgroundColor: COLORS.background,
  },
  header: {
    backgroundColor: COLORS.dark,
    alignItems: 'center',
    justifyContent: 'center',
    paddingTop: SPACING.xxxl,
    paddingBottom: SPACING.xxl,
    paddingHorizontal: SPACING.lg,
    borderBottomLeftRadius: RADIUS.xl,
    borderBottomRightRadius: RADIUS.xl,
  },
  card: {
    margin: SPACING.lg,
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.lg,
    padding: SPACING.lg,
    gap: SPACING.xs,
    shadowColor: '#1C2422',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.09,
    shadowRadius: 8,
    elevation: 4,
  },
  cardTitle: {
    fontSize: FONT.titleSize,
    fontWeight: FONT.titleWeight,
    color: COLORS.textPrimary,
    marginBottom: SPACING.sm,
  },
  hint: {
    fontSize: FONT.bodySize,
    color: COLORS.textSecondary,
    marginBottom: SPACING.sm,
  },
  neutralMessage: {
    fontSize: FONT.bodySize,
    color: COLORS.textPrimary,
    lineHeight: 22,
  },
  button: {
    marginTop: SPACING.sm,
  },
});

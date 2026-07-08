import React, { useRef, useState } from 'react';
import { StyleSheet, Text, TextInput, View, ScrollView } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { AppButton } from '@/components/AppButton';
import { PasswordInput } from '@/components/PasswordInput';
import { FormError } from '@/components/FormError';
import { AppLogo } from '@/components/AppLogo';
import { authApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { validateResetPasswordForm } from '@/validation/passwordResetSchema';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

function resetPasswordErrorMessage(err: ApiError): string {
  switch (err.normalized.code) {
    case 'AUTH_RESET_TOKEN_INVALID':
      return 'El enlace ya no es válido o expiró. Solicitá uno nuevo.';
    default:
      break;
  }
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

export function ResetPasswordScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{ token?: string; email?: string }>();
  const token = typeof params.token === 'string' ? params.token : '';
  const email = typeof params.email === 'string' ? decodeURIComponent(params.email) : '';

  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [fieldErrors, setFieldErrors] = useState<{ password?: string; passwordConfirmation?: string }>({});
  const [generalError, setGeneralError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);
  const confirmRef = useRef<TextInput>(null);

  const missingLinkData = !token || !email;

  async function handleSubmit() {
    if (submitting || missingLinkData) return;
    setGeneralError(null);
    setFieldErrors({});

    const errors = validateResetPasswordForm({ password, passwordConfirmation });
    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors);
      return;
    }

    setSubmitting(true);
    try {
      await authApi.resetPassword({
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      setSuccess(true);
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.normalized.status === 422 && Object.keys(err.normalized.fieldErrors).length > 0 && err.normalized.code !== 'AUTH_RESET_TOKEN_INVALID') {
          const fe: typeof fieldErrors = {};
          if (err.normalized.fieldErrors.password) fe.password = err.normalized.fieldErrors.password[0];
          setFieldErrors(fe);
          if (Object.keys(fe).length === 0) setGeneralError(resetPasswordErrorMessage(err));
        } else {
          setGeneralError(resetPasswordErrorMessage(err));
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
        <Text style={styles.cardTitle}>Nueva contraseña</Text>

        {success ? (
          <>
            <Text style={styles.neutralMessage} accessibilityLiveRegion="polite">
              Contraseña restablecida correctamente. Ya podés iniciar sesión.
            </Text>
            <AppButton
              title="Ir al login"
              onPress={() => router.replace('/(auth)/login' as never)}
              fullWidth
              style={styles.button}
            />
          </>
        ) : missingLinkData ? (
          <>
            <Text style={styles.neutralMessage}>
              El enlace de recuperación es inválido o está incompleto. Solicitá uno nuevo desde la pantalla de login.
            </Text>
            <AppButton
              title="Solicitar nuevo enlace"
              onPress={() => router.replace('/(auth)/forgot-password' as never)}
              fullWidth
              style={styles.button}
            />
          </>
        ) : (
          <>
            <Text style={styles.hint}>Ingresá tu nueva contraseña para {email}.</Text>

            <FormError message={generalError} />

            <PasswordInput
              label="Nueva contraseña"
              value={password}
              onChangeText={(t) => { setPassword(t); setFieldErrors((f) => ({ ...f, password: undefined })); }}
              error={fieldErrors.password}
              returnKeyType="next"
              onSubmitEditing={() => confirmRef.current?.focus()}
              editable={!submitting}
            />

            <PasswordInput
              ref={confirmRef}
              label="Repetir contraseña"
              value={passwordConfirmation}
              onChangeText={(t) => { setPasswordConfirmation(t); setFieldErrors((f) => ({ ...f, passwordConfirmation: undefined })); }}
              error={fieldErrors.passwordConfirmation}
              returnKeyType="done"
              onSubmitEditing={handleSubmit}
              editable={!submitting}
            />

            <AppButton
              title={submitting ? 'Guardando...' : 'Restablecer contraseña'}
              onPress={handleSubmit}
              loading={submitting}
              disabled={submitting}
              fullWidth
              style={styles.button}
            />
          </>
        )}
      </View>
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
    marginBottom: SPACING.sm,
  },
  button: {
    marginTop: SPACING.sm,
  },
});

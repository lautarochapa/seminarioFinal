import React, { useRef, useState } from 'react';
import {
  StyleSheet,
  Text,
  TextInput,
  View,
  ScrollView,
  Pressable,
} from 'react-native';
import { useRouter } from 'expo-router';
import { AppButton } from '@/components/AppButton';
import { AppInput } from '@/components/AppInput';
import { PasswordInput } from '@/components/PasswordInput';
import { FormError } from '@/components/FormError';
import { AppLogo } from '@/components/AppLogo';
import { AuthFooterLink } from '@/components/AuthFooterLink';
import { useAuth } from '@/auth/AuthContext';
import { ApiError } from '@/api/client';
import { validateLoginForm } from '@/validation/loginSchema';
import { ENV } from '@/config/env';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

function loginErrorMessage(err: ApiError): string {
  switch (err.normalized.status) {
    case 401: return 'Email o contraseña incorrectos.';
    case 422: return err.normalized.message;
    case 429: return `Demasiados intentos. Esperá${err.normalized.retryAfter ? ` ${err.normalized.retryAfter}s` : ' un momento'} e intentá de nuevo.`;
    case 500: return 'Error del servidor. Intentá más tarde.';
    case 0:
      if (err.normalized.isTimeoutError) return 'La solicitud tardó demasiado. Verificá tu conexión.';
      return 'No se pudo conectar al servidor. Verificá tu conexión.';
    default: return err.normalized.message;
  }
}

export function LoginScreen() {
  const router = useRouter();
  const { login, isLoading } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [fieldErrors, setFieldErrors] = useState<{ email?: string; password?: string }>({});
  const [generalError, setGeneralError] = useState<string | null>(null);
  const [retryAfter, setRetryAfter] = useState<number | null>(null);
  const passwordRef = useRef<TextInput>(null);

  const canSubmit = !isLoading && retryAfter === null;

  async function handleSubmit() {
    setGeneralError(null);
    setFieldErrors({});

    const errors = validateLoginForm({ email: email.trim(), password });
    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors);
      return;
    }

    try {
      await login({ email: email.trim(), password });
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.normalized.status === 422 && Object.keys(err.normalized.fieldErrors).length > 0) {
          const fe: { email?: string; password?: string } = {};
          if (err.normalized.fieldErrors.email) fe.email = err.normalized.fieldErrors.email[0];
          if (err.normalized.fieldErrors.password) fe.password = err.normalized.fieldErrors.password[0];
          setFieldErrors(fe);
        } else {
          setGeneralError(loginErrorMessage(err));
          if (err.normalized.status === 429 && err.normalized.retryAfter) {
            setRetryAfter(err.normalized.retryAfter);
            const timer = setTimeout(() => setRetryAfter(null), err.normalized.retryAfter * 1000);
            return () => clearTimeout(timer);
          }
        }
      } else {
        setGeneralError('Ocurrió un error inesperado.');
      }
    }
  }

  function fillDemo(demoEmail: string, demoPassword: string) {
    setEmail(demoEmail);
    setPassword(demoPassword);
    setGeneralError(null);
    setFieldErrors({});
  }

  return (
    <ScrollView
      contentContainerStyle={styles.scroll}
      keyboardShouldPersistTaps="handled"
      showsVerticalScrollIndicator={false}
    >
      {/* Brand header */}
      <View style={styles.header}>
        <AppLogo variant="large" inverted />
      </View>

      {/* Form card */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Iniciar sesión</Text>

        <FormError message={generalError} />

        <AppInput
          label="Email"
          value={email}
          onChangeText={(t) => { setEmail(t); setFieldErrors((f) => ({ ...f, email: undefined })); }}
          error={fieldErrors.email}
          keyboardType="email-address"
          autoCapitalize="none"
          autoCorrect={false}
          returnKeyType="next"
          onSubmitEditing={() => passwordRef.current?.focus()}
          editable={!isLoading}
        />

        <PasswordInput
          ref={passwordRef}
          label="Contraseña"
          value={password}
          onChangeText={(t) => { setPassword(t); setFieldErrors((f) => ({ ...f, password: undefined })); }}
          error={fieldErrors.password}
          returnKeyType="done"
          onSubmitEditing={handleSubmit}
          editable={!isLoading}
        />

        <Pressable
          style={styles.forgotLink}
          onPress={() => router.push('/(auth)/forgot-password' as never)}
          accessibilityRole="button"
          accessibilityLabel="¿Olvidaste tu contraseña?"
        >
          <Text style={styles.forgotLinkText}>¿Olvidaste tu contraseña?</Text>
        </Pressable>

        <AppButton
          title={retryAfter ? `Esperá ${retryAfter}s` : 'Iniciar sesión'}
          onPress={handleSubmit}
          loading={isLoading}
          disabled={!canSubmit}
          fullWidth
          style={styles.button}
        />
      </View>

      <AuthFooterLink
        prompt="¿No tenés cuenta?"
        actionLabel="Crear cuenta"
        onPress={() => router.push('/(auth)/register' as never)}
      />

      {ENV.SHOW_DEMO_USERS ? (
        <View style={styles.demo}>
          <Text style={styles.demoTitle}>— Usuarios demo —</Text>
          {[
            { email: 'usuario@cccontrol.test', password: '12345678' },
            { email: 'superadmin@cccontrol.test', password: '12345678' },
            { email: 'admin@cccontrol.test', password: 'password123' },
          ].map((u) => (
            <Pressable
              key={u.email}
              style={styles.demoRow}
              onPress={() => fillDemo(u.email, u.password)}
              accessibilityRole="button"
              accessibilityLabel={`Usar demo ${u.email}`}
            >
              <Text style={styles.demoEmail}>{u.email}</Text>
              <Text style={styles.demoHint}>contraseña: {u.password}</Text>
            </Pressable>
          ))}
        </View>
      ) : null}
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
  button: {
    marginTop: SPACING.sm,
  },
  forgotLink: {
    alignSelf: 'flex-end',
    paddingVertical: SPACING.xs,
  },
  forgotLinkText: {
    fontSize: FONT.captionSize,
    color: COLORS.primary,
    fontWeight: '600',
  },
  demo: {
    marginHorizontal: SPACING.lg,
    marginBottom: SPACING.xxl,
    padding: SPACING.md,
    backgroundColor: COLORS.warningLight,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: '#F9A825',
    gap: SPACING.sm,
  },
  demoTitle: {
    fontSize: FONT.captionSize,
    color: '#6D4C41',
    textAlign: 'center',
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
  },
  demoRow: {
    paddingVertical: SPACING.xs,
  },
  demoEmail: {
    fontSize: FONT.labelSize,
    color: COLORS.primary,
    fontWeight: '600',
  },
  demoHint: {
    fontSize: FONT.captionSize,
    color: COLORS.textSecondary,
  },
});

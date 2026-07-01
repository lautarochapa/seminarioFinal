import React, { useRef, useState } from 'react';
import {
  StyleSheet,
  Text,
  TextInput,
  View,
  ScrollView,
  TouchableOpacity,
} from 'react-native';
import { AppButton } from '@/components/AppButton';
import { AppInput } from '@/components/AppInput';
import { PasswordInput } from '@/components/PasswordInput';
import { FormError } from '@/components/FormError';
import { useAuth } from '@/auth/AuthContext';
import { ApiError } from '@/api/client';
import { validateLoginForm } from '@/validation/loginSchema';
import { ENV } from '@/config/env';
import { COLORS, FONT_SIZE, SPACING } from '@/utils/theme';

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
  const { login, isLoading } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [fieldErrors, setFieldErrors] = useState<{ email?: string; password?: string }>({});
  const [generalError, setGeneralError] = useState<string | null>(null);
  const [retryAfter, setRetryAfter] = useState<number | null>(null);
  const passwordRef = useRef<TextInput>(null);

  const canSubmit = !isLoading && (retryAfter === null);

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
      <View style={styles.header}>
        <Text style={styles.logo}>🌿</Text>
        <Text style={styles.appName}>CocinaComidaControl</Text>
        <Text style={styles.tagline}>Tu cocina, tu presupuesto, tu familia.</Text>
      </View>

      <View style={styles.form}>
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

        <AppButton
          title={retryAfter ? `Esperá ${retryAfter}s` : 'Iniciar sesión'}
          onPress={handleSubmit}
          loading={isLoading}
          disabled={!canSubmit}
          style={styles.button}
        />
      </View>

      {ENV.SHOW_DEMO_USERS ? (
        <View style={styles.demo}>
          <Text style={styles.demoTitle}>— Usuarios demo —</Text>
          <TouchableOpacity
            style={styles.demoRow}
            onPress={() => fillDemo('usuario@cccontrol.test', '12345678')}
          >
            <Text style={styles.demoEmail}>usuario@cccontrol.test</Text>
            <Text style={styles.demoHint}>contraseña: 12345678</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.demoRow}
            onPress={() => fillDemo('superadmin@cccontrol.test', '12345678')}
          >
            <Text style={styles.demoEmail}>superadmin@cccontrol.test</Text>
            <Text style={styles.demoHint}>contraseña: 12345678</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.demoRow}
            onPress={() => fillDemo('admin@cccontrol.test', 'password123')}
          >
            <Text style={styles.demoEmail}>admin@cccontrol.test</Text>
            <Text style={styles.demoHint}>contraseña: password123</Text>
          </TouchableOpacity>
        </View>
      ) : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: {
    flexGrow: 1,
    paddingHorizontal: SPACING.lg,
    paddingTop: SPACING.xxl,
    paddingBottom: SPACING.xxl,
    backgroundColor: COLORS.background,
  },
  header: {
    alignItems: 'center',
    marginBottom: SPACING.xxl,
  },
  logo: {
    fontSize: 56,
    marginBottom: SPACING.sm,
  },
  appName: {
    fontSize: FONT_SIZE.xl,
    fontWeight: '700',
    color: COLORS.primary,
    marginBottom: SPACING.xs,
  },
  tagline: {
    fontSize: FONT_SIZE.sm,
    color: COLORS.textSecondary,
  },
  form: {
    gap: SPACING.xs,
  },
  button: {
    marginTop: SPACING.sm,
  },
  demo: {
    marginTop: SPACING.xxl,
    padding: SPACING.md,
    backgroundColor: '#FFF9C4',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#F9A825',
    gap: SPACING.sm,
  },
  demoTitle: {
    fontSize: FONT_SIZE.xs,
    color: '#6D4C41',
    textAlign: 'center',
    fontWeight: '700',
    textTransform: 'uppercase',
  },
  demoRow: {
    paddingVertical: SPACING.xs,
  },
  demoEmail: {
    fontSize: FONT_SIZE.sm,
    color: COLORS.primary,
    fontWeight: '500',
  },
  demoHint: {
    fontSize: FONT_SIZE.xs,
    color: COLORS.textSecondary,
  },
});

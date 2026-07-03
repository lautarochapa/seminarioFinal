import React, { useRef, useState } from 'react';
import { StyleSheet, Text, TextInput, View, ScrollView } from 'react-native';
import { useRouter } from 'expo-router';
import { AppButton } from '@/components/AppButton';
import { AppInput } from '@/components/AppInput';
import { PasswordInput } from '@/components/PasswordInput';
import { FormError } from '@/components/FormError';
import { AppLogo } from '@/components/AppLogo';
import { AuthFooterLink } from '@/components/AuthFooterLink';
import { useAuth } from '@/auth/AuthContext';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { ApiError } from '@/api/client';
import { familyGroupsApi } from '@/api/endpoints';
import { validateRegisterForm } from '@/validation/registerSchema';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

function registerErrorMessage(err: ApiError): string {
  switch (err.normalized.status) {
    case 409: return 'Ya existe una cuenta con ese email.';
    case 422: return err.normalized.message;
    case 429: return `Demasiados intentos. Esperá${err.normalized.retryAfter ? ` ${err.normalized.retryAfter}s` : ' un momento'} e intentá de nuevo.`;
    case 500: return 'Error del servidor. Intentá más tarde.';
    case 0:
      if (err.normalized.isTimeoutError) return 'La solicitud tardó demasiado. Verificá tu conexión.';
      return 'No se pudo conectar al servidor. Verificá tu conexión.';
    default: return err.normalized.message;
  }
}

export function RegisterScreen() {
  const router = useRouter();
  const { register } = useAuth();
  const { restoreGroup } = useFamilyGroupContext();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [fieldErrors, setFieldErrors] = useState<{ name?: string; email?: string; password?: string; passwordConfirmation?: string }>({});
  const [generalError, setGeneralError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const emailRef = useRef<TextInput>(null);
  const passwordRef = useRef<TextInput>(null);
  const confirmRef = useRef<TextInput>(null);

  async function handleSubmit() {
    if (submitting) return;
    setGeneralError(null);
    setFieldErrors({});

    const errors = validateRegisterForm({ name: name.trim(), email: email.trim(), password, passwordConfirmation });
    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors);
      return;
    }

    setSubmitting(true);
    try {
      await register({
        name: name.trim(),
        email: email.trim(),
        password,
        password_confirmation: passwordConfirmation,
      });

      // Sesión ya iniciada por register(). Decidimos a dónde navegar según los
      // grupos familiares existentes del usuario nuevo.
      try {
        const groupsRes = await familyGroupsApi.list();
        const groups = groupsRes.data ?? [];
        await restoreGroup(groups);
        if (groups.length === 0) {
          router.replace('/(app)/groups' as never);
        } else {
          router.replace('/(app)' as never);
        }
      } catch {
        router.replace('/(app)' as never);
      }
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.normalized.status === 422 && Object.keys(err.normalized.fieldErrors).length > 0) {
          const fe: typeof fieldErrors = {};
          if (err.normalized.fieldErrors.name) fe.name = err.normalized.fieldErrors.name[0];
          if (err.normalized.fieldErrors.email) fe.email = err.normalized.fieldErrors.email[0];
          if (err.normalized.fieldErrors.password) fe.password = err.normalized.fieldErrors.password[0];
          setFieldErrors(fe);
          if (Object.keys(fe).length === 0) setGeneralError(registerErrorMessage(err));
        } else {
          setGeneralError(registerErrorMessage(err));
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
        <Text style={styles.cardTitle}>Crear cuenta</Text>

        <FormError message={generalError} />

        <AppInput
          label="Nombre"
          value={name}
          onChangeText={(t) => { setName(t); setFieldErrors((f) => ({ ...f, name: undefined })); }}
          error={fieldErrors.name}
          autoCapitalize="words"
          returnKeyType="next"
          onSubmitEditing={() => emailRef.current?.focus()}
          editable={!submitting}
        />

        <AppInput
          ref={emailRef}
          label="Email"
          value={email}
          onChangeText={(t) => { setEmail(t); setFieldErrors((f) => ({ ...f, email: undefined })); }}
          error={fieldErrors.email}
          keyboardType="email-address"
          autoCapitalize="none"
          autoCorrect={false}
          returnKeyType="next"
          onSubmitEditing={() => passwordRef.current?.focus()}
          editable={!submitting}
        />

        <PasswordInput
          ref={passwordRef}
          label="Contraseña"
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
          title="Crear cuenta"
          onPress={handleSubmit}
          loading={submitting}
          disabled={submitting}
          fullWidth
          style={styles.button}
        />
      </View>

      <AuthFooterLink
        prompt="¿Ya tenés cuenta?"
        actionLabel="Iniciar sesión"
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
  button: {
    marginTop: SPACING.sm,
  },
});

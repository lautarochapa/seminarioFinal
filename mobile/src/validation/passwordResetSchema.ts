export interface ForgotPasswordFormValues {
  email: string;
}

export interface ForgotPasswordValidationErrors {
  email?: string;
}

export function validateForgotPasswordForm(values: ForgotPasswordFormValues): ForgotPasswordValidationErrors {
  const errors: ForgotPasswordValidationErrors = {};

  if (!values.email.trim()) {
    errors.email = 'El email es obligatorio.';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email.trim())) {
    errors.email = 'Ingresá un email válido.';
  }

  return errors;
}

export interface ResetPasswordFormValues {
  password: string;
  passwordConfirmation: string;
}

export interface ResetPasswordValidationErrors {
  password?: string;
  passwordConfirmation?: string;
}

export function validateResetPasswordForm(values: ResetPasswordFormValues): ResetPasswordValidationErrors {
  const errors: ResetPasswordValidationErrors = {};

  if (!values.password) {
    errors.password = 'La contraseña es obligatoria.';
  } else if (values.password.length < 8) {
    errors.password = 'La contraseña debe tener al menos 8 caracteres.';
  }

  if (!values.passwordConfirmation) {
    errors.passwordConfirmation = 'Repetí la contraseña.';
  } else if (values.password && values.passwordConfirmation !== values.password) {
    errors.passwordConfirmation = 'Las contraseñas no coinciden.';
  }

  return errors;
}

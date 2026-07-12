export interface RegisterFormValues {
  name: string;
  email: string;
  password: string;
  passwordConfirmation: string;
}

export interface RegisterValidationErrors {
  name?: string;
  email?: string;
  password?: string;
  passwordConfirmation?: string;
}

export function validateRegisterForm(values: RegisterFormValues): RegisterValidationErrors {
  const errors: RegisterValidationErrors = {};

  if (!values.name.trim()) {
    errors.name = 'El nombre es obligatorio.';
  }

  if (!values.email.trim()) {
    errors.email = 'El email es obligatorio.';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email.trim())) {
    errors.email = 'Ingresá un email válido.';
  }

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

import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import { LoginScreen } from '../src/screens/LoginScreen';
import { ForgotPasswordScreen } from '../src/screens/ForgotPasswordScreen';
import { ResetPasswordScreen } from '../src/screens/ResetPasswordScreen';
import { ApiError } from '../src/api/client';

jest.mock('@expo/vector-icons', () => ({
  MaterialCommunityIcons: 'MaterialCommunityIcons',
}));

const mockPush = jest.fn();
const mockReplace = jest.fn();
let mockParams: { token?: string; email?: string } = {};
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: mockPush, replace: mockReplace }),
  useLocalSearchParams: () => mockParams,
}));

jest.mock('../src/auth/AuthContext', () => ({
  useAuth: () => ({ login: jest.fn(), register: jest.fn(), isLoading: false }),
}));

jest.mock('../src/config/env', () => ({ ENV: { SHOW_DEMO_USERS: false, API_URL: 'http://test' } }));

const mockForgotPassword = jest.fn();
const mockResetPassword = jest.fn();
jest.mock('../src/api/endpoints', () => ({
  authApi: {
    forgotPassword: (...args: unknown[]) => mockForgotPassword(...args),
    resetPassword: (...args: unknown[]) => mockResetPassword(...args),
  },
}));

beforeEach(() => {
  jest.clearAllMocks();
  mockParams = {};
});

describe('LoginScreen — forgot password link', () => {
  it('shows a visible "¿Olvidaste tu contraseña?" link that navigates to forgot-password', async () => {
    const { getByRole } = await render(<LoginScreen />);
    const link = getByRole('button', { name: '¿Olvidaste tu contraseña?' });
    expect(link).toBeTruthy();
    await fireEvent.press(link);
    expect(mockPush).toHaveBeenCalledWith('/(auth)/forgot-password');
  });
});

describe('ForgotPasswordScreen', () => {
  it('sends the request and shows a neutral confirmation message', async () => {
    mockForgotPassword.mockResolvedValue({ data: { message: 'ok' }, trace_id: 't1' });
    const { getByLabelText, getByRole, findByText } = await render(<ForgotPasswordScreen />);

    await fireEvent.changeText(getByLabelText('Email'), 'ana@test.com');
    await fireEvent.press(getByRole('button', { name: 'Enviar instrucciones' }));

    expect(mockForgotPassword).toHaveBeenCalledWith({ email: 'ana@test.com' });
    expect(await findByText(/Si el correo está registrado/)).toBeTruthy();
  });

  it('shows the same neutral message regardless of whether the email exists', async () => {
    mockForgotPassword.mockResolvedValue({ data: { message: 'ok' }, trace_id: 't1' });
    const { getByLabelText, getByRole, findByText, queryByText } = await render(<ForgotPasswordScreen />);

    await fireEvent.changeText(getByLabelText('Email'), 'noexiste@test.com');
    await fireEvent.press(getByRole('button', { name: 'Enviar instrucciones' }));

    expect(await findByText(/Si el correo está registrado/)).toBeTruthy();
    expect(queryByText(/no existe|no encontrado/i)).toBeNull();
  });

  it('shows a validation error for an invalid email (422)', async () => {
    mockForgotPassword.mockRejectedValue(new ApiError({
      status: 422, code: 'VALIDATION_ERROR', message: 'Datos inválidos.',
      fieldErrors: { email: ['El email es inválido.'] }, traceId: 't1', isNetworkError: false, isTimeoutError: false,
    }));
    const { getByLabelText, getByRole, findByText } = await render(<ForgotPasswordScreen />);

    await fireEvent.changeText(getByLabelText('Email'), 'ana@test.com');
    await fireEvent.press(getByRole('button', { name: 'Enviar instrucciones' }));

    expect(await findByText('El email es inválido.')).toBeTruthy();
  });

  it('shows a rate limit message on 429', async () => {
    mockForgotPassword.mockRejectedValue(new ApiError({
      status: 429, code: 'RATE_LIMITED', message: 'Too many requests.',
      fieldErrors: {}, traceId: 't1', isNetworkError: false, isTimeoutError: false, retryAfter: 30,
    }));
    const { getByLabelText, getByRole, findByText } = await render(<ForgotPasswordScreen />);

    await fireEvent.changeText(getByLabelText('Email'), 'ana@test.com');
    await fireEvent.press(getByRole('button', { name: 'Enviar instrucciones' }));

    expect(await findByText(/Demasiados intentos/)).toBeTruthy();
  });
});

describe('ResetPasswordScreen — deep link params', () => {
  it('shows an invalid-link message when token/email are missing from the deep link', async () => {
    mockParams = {};
    const { findByText } = await render(<ResetPasswordScreen />);
    expect(await findByText(/enlace de recuperación es inválido/)).toBeTruthy();
  });

  it('resets the password successfully when token and email are present', async () => {
    mockParams = { token: 'abc123', email: encodeURIComponent('ana@test.com') };
    mockResetPassword.mockResolvedValue({ data: { message: 'ok' }, trace_id: 't1' });
    const { getByLabelText, getByRole, findByText } = await render(<ResetPasswordScreen />);

    await fireEvent.changeText(getByLabelText('Nueva contraseña'), 'nuevapass123');
    await fireEvent.changeText(getByLabelText('Repetir contraseña'), 'nuevapass123');
    await fireEvent.press(getByRole('button', { name: 'Restablecer contraseña' }));

    await waitFor(() => {
      expect(mockResetPassword).toHaveBeenCalledWith({
        token: 'abc123',
        email: 'ana@test.com',
        password: 'nuevapass123',
        password_confirmation: 'nuevapass123',
      });
    });
    expect(await findByText(/Contraseña restablecida correctamente/)).toBeTruthy();
  });

  it('shows an expired/invalid token message', async () => {
    mockParams = { token: 'expired', email: encodeURIComponent('ana@test.com') };
    mockResetPassword.mockRejectedValue(new ApiError({
      status: 422, code: 'AUTH_RESET_TOKEN_INVALID', message: 'El token es inválido o expiró.',
      fieldErrors: {}, traceId: 't1', isNetworkError: false, isTimeoutError: false,
    }));
    const { getByLabelText, getByRole, findByText } = await render(<ResetPasswordScreen />);

    await fireEvent.changeText(getByLabelText('Nueva contraseña'), 'nuevapass123');
    await fireEvent.changeText(getByLabelText('Repetir contraseña'), 'nuevapass123');
    await fireEvent.press(getByRole('button', { name: 'Restablecer contraseña' }));

    expect(await findByText(/enlace ya no es válido o expiró/)).toBeTruthy();
  });

  it('shows a validation error when passwords do not match', async () => {
    mockParams = { token: 'abc123', email: encodeURIComponent('ana@test.com') };
    const { getByLabelText, getByRole, findByText } = await render(<ResetPasswordScreen />);

    await fireEvent.changeText(getByLabelText('Nueva contraseña'), 'nuevapass123');
    await fireEvent.changeText(getByLabelText('Repetir contraseña'), 'otradistinta');
    await fireEvent.press(getByRole('button', { name: 'Restablecer contraseña' }));

    expect(await findByText('Las contraseñas no coinciden.')).toBeTruthy();
    expect(mockResetPassword).not.toHaveBeenCalled();
  });
});

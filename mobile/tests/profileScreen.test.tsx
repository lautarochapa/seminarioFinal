import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { ProfileScreen } from '../src/screens/ProfileScreen';
import { ApiError } from '../src/api/client';

const mockGet = jest.fn();
const mockUpdate = jest.fn();
const mockRefreshUser = jest.fn();
jest.mock('../src/api/endpoints', () => ({
  profileApi: { get: (...args: unknown[]) => mockGet(...args), update: (...args: unknown[]) => mockUpdate(...args) },
}));
jest.mock('../src/auth/AuthContext', () => ({
  useAuth: () => ({ logout: jest.fn(), isLoading: false, refreshCurrentUser: mockRefreshUser }),
}));
jest.mock('expo-router', () => ({ useRouter: () => ({ push: jest.fn() }) }));
jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));

const PROFILE = {
  id: 4, name: 'Martina', lastname: null, email: 'qa@example.invalid',
  phone: null, notes: null, current_weight_kg: null, height_cm: 170,
  objectives: [], preferences: { uses_app_for_health: false, uses_app_for_budget: true, uses_app_for_organization: false },
};

beforeEach(() => {
  jest.clearAllMocks();
  mockGet.mockResolvedValue({ data: { ...PROFILE } });
  mockUpdate.mockResolvedValue({ data: PROFILE });
  mockRefreshUser.mockResolvedValue(undefined);
});

it('opens a profile without lastname or optional weight and keeps editing usable', async () => {
  const screen = await render(<ProfileScreen />);
  await waitFor(() => expect(screen.getByText('qa@example.invalid')).toBeTruthy());
  await fireEvent.press(screen.getByText('Editar datos'));
  expect(screen.getByLabelText('Apellido').props.value).toBe('');
  await fireEvent.press(screen.getByText('Guardar'));
  await waitFor(() => expect(mockUpdate).toHaveBeenCalledWith({ name: 'Martina', lastname: '', phone: null, notes: null }));
  await waitFor(() => expect(screen.getByText('Perfil actualizado correctamente.')).toBeTruthy());
  expect(mockRefreshUser).toHaveBeenCalledTimes(1);
  expect(mockGet).toHaveBeenCalledTimes(2);
});

it('preserves form values and shows validation errors without announcing success', async () => {
  mockGet.mockResolvedValue({ data: { ...PROFILE, lastname: 'Lopez' } });
  mockUpdate.mockRejectedValue(new ApiError({ status: 422, code: 'VALIDATION_ERROR', message: 'Revisa el nombre.', fieldErrors: { name: ['Nombre obligatorio.'] }, traceId: 'qa', isNetworkError: false, isTimeoutError: false }));
  const screen = await render(<ProfileScreen />);
  await waitFor(() => expect(screen.getByText('Editar datos')).toBeTruthy());
  await fireEvent.press(screen.getByText('Editar datos'));
  await fireEvent.changeText(screen.getByLabelText('Nombre'), '');
  await fireEvent.press(screen.getByText('Guardar'));
  await waitFor(() => expect(screen.getByText('Nombre obligatorio.')).toBeTruthy());
  expect(screen.getByLabelText('Apellido').props.value).toBe('Lopez');
  expect(screen.queryByText('Perfil actualizado correctamente.')).toBeNull();
  expect(mockRefreshUser).not.toHaveBeenCalled();
});

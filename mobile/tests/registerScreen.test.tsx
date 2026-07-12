import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import { RegisterScreen } from '../src/screens/RegisterScreen';
import { LoginScreen } from '../src/screens/LoginScreen';
import { ApiError } from '../src/api/client';

jest.mock('@expo/vector-icons', () => ({
  MaterialCommunityIcons: 'MaterialCommunityIcons',
}));

const mockPush = jest.fn();
const mockReplace = jest.fn();
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: mockPush, replace: mockReplace }),
}));

const mockRegister = jest.fn();
jest.mock('../src/auth/AuthContext', () => ({
  useAuth: () => ({ register: mockRegister, login: jest.fn(), isLoading: false }),
}));

const mockRestoreGroup = jest.fn();
jest.mock('../src/auth/FamilyGroupContext', () => ({
  useFamilyGroupContext: () => ({ restoreGroup: mockRestoreGroup, selectGroup: jest.fn(), selectedGroup: null }),
}));

const mockFamilyGroupsList = jest.fn();
jest.mock('../src/api/endpoints', () => ({
  familyGroupsApi: { list: (...args: unknown[]) => mockFamilyGroupsList(...args) },
}));

jest.mock('../src/config/env', () => ({ ENV: { SHOW_DEMO_USERS: false, API_URL: 'http://test' } }));

beforeEach(() => {
  jest.clearAllMocks();
  mockFamilyGroupsList.mockResolvedValue({ data: [] });
});

describe('LoginScreen — register link', () => {
  it('shows a visible "Crear cuenta" link that navigates to /(auth)/register', async () => {
    const { getByText } = await render(<LoginScreen />);
    const link = getByText('Crear cuenta');
    expect(link).toBeTruthy();
    await fireEvent.press(link);
    expect(mockPush).toHaveBeenCalledWith('/(auth)/register');
  });
});

describe('RegisterScreen', () => {
  it('validates required fields before submitting', async () => {
    const { getByRole } = await render(<RegisterScreen />);
    await fireEvent.press(getByRole('button', { name: 'Crear cuenta' }));
    expect(mockRegister).not.toHaveBeenCalled();
  });

  it('registers successfully and navigates to the app when the user has no groups', async () => {
    mockRegister.mockResolvedValue(undefined);
    const { getByLabelText, getByRole } = await render(<RegisterScreen />);

    await fireEvent.changeText(getByLabelText('Nombre'), 'Ana');
    await fireEvent.changeText(getByLabelText('Email'), 'ana@test.com');
    await fireEvent.changeText(getByLabelText('Contraseña'), 'password123');
    await fireEvent.changeText(getByLabelText('Repetir contraseña'), 'password123');

    expect(getByLabelText('Nombre').props.value).toBe('Ana');
    expect(getByLabelText('Contraseña').props.value).toBe('password123');
    expect(getByLabelText('Repetir contraseña').props.value).toBe('password123');

    await fireEvent.press(getByRole('button', { name: 'Crear cuenta' }));

    await waitFor(() => {
      expect(mockRegister).toHaveBeenCalledWith({
        name: 'Ana',
        email: 'ana@test.com',
        password: 'password123',
        password_confirmation: 'password123',
      });
    });

    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith('/(app)/groups');
    });
  });

  it('navigates to home directly when the user already has a group', async () => {
    mockRegister.mockResolvedValue(undefined);
    mockFamilyGroupsList.mockResolvedValue({ data: [{ id: 1, name: 'Grupo' }] });
    const { getByLabelText, getByRole } = await render(<RegisterScreen />);

    await fireEvent.changeText(getByLabelText('Nombre'), 'Ana');
    await fireEvent.changeText(getByLabelText('Email'), 'ana@test.com');
    await fireEvent.changeText(getByLabelText('Contraseña'), 'password123');
    await fireEvent.changeText(getByLabelText('Repetir contraseña'), 'password123');
    await fireEvent.press(getByRole('button', { name: 'Crear cuenta' }));

    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith('/(app)');
    });
  });

  it('shows a duplicate email message on 409', async () => {
    mockRegister.mockRejectedValue(new ApiError({
      status: 409, code: 'AUTH_EMAIL_ALREADY_EXISTS', message: 'Email ya registrado.',
      fieldErrors: {}, traceId: 't1', isNetworkError: false, isTimeoutError: false,
    }));
    const { getByLabelText, getByRole, findByText } = await render(<RegisterScreen />);

    await fireEvent.changeText(getByLabelText('Nombre'), 'Ana');
    await fireEvent.changeText(getByLabelText('Email'), 'ana@test.com');
    await fireEvent.changeText(getByLabelText('Contraseña'), 'password123');
    await fireEvent.changeText(getByLabelText('Repetir contraseña'), 'password123');
    await fireEvent.press(getByRole('button', { name: 'Crear cuenta' }));

    expect(await findByText('Ya existe una cuenta con ese email.')).toBeTruthy();
    expect(mockReplace).not.toHaveBeenCalled();
  });
});

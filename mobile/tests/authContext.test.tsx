import React from 'react';
import { act, renderHook, waitFor } from '@testing-library/react-native';
import { AuthProvider, useAuth } from '../src/auth/AuthContext';
import { ApiError } from '../src/api/client';

const mockMe = jest.fn();
const mockLogin = jest.fn();
const mockRegister = jest.fn();
const mockLogout = jest.fn();
const mockGetSession = jest.fn();
const mockSave = jest.fn();
const mockClear = jest.fn();
const mockClearCache = jest.fn();
jest.mock('../src/api/endpoints', () => ({ authApi: {
  me: (...args: unknown[]) => mockMe(...args), login: (...args: unknown[]) => mockLogin(...args),
  register: (...args: unknown[]) => mockRegister(...args), logout: (...args: unknown[]) => mockLogout(...args),
} }));
jest.mock('../src/storage/sessionStorage', () => ({ sessionStorage: {
  getSession: () => mockGetSession(), save: (...args: unknown[]) => mockSave(...args), clear: () => mockClear(),
} }));
jest.mock('../src/storage/offlineCache', () => ({ offlineCache: { clearAll: () => mockClearCache() } }));

const USER = { id: 4, name: 'Martina', lastname: null, email: 'qa@example.invalid', roles: ['user'], permissions: [] };
const RESPONSE = { data: USER, token: { access_token: 'fake-qa-token' } };
const wrapper = ({ children }: { children: React.ReactNode }) => <AuthProvider>{children}</AuthProvider>;

it('rejects an administrative login response before persisting credentials', async () => {
  mockLogin.mockResolvedValue({ ...RESPONSE, data: { ...USER, roles: ['super_admin'] } });
  const { result } = await renderHook(() => useAuth(), { wrapper });
  await waitFor(() => expect(result.current.state).toBe('unauthenticated'));
  await act(async () => {
    await expect(result.current.login({ email: USER.email, password: 'fake-qa-password' })).rejects.toThrow();
  });
  expect(mockSave).not.toHaveBeenCalled();
  expect(result.current.state).toBe('unauthenticated');
});

it('clears a previously saved session when the account is now an administrator', async () => {
  mockGetSession.mockResolvedValue({ accessToken: 'fake-stored-token', user: USER });
  mockMe.mockResolvedValue({ data: { ...USER, roles: ['catalog_admin'] } });
  const { result } = await renderHook(() => useAuth(), { wrapper });
  await waitFor(() => expect(result.current.state).toBe('unauthenticated'));
  expect(mockClear).toHaveBeenCalled();
  expect(mockClearCache).toHaveBeenCalled();
  expect(mockSave).not.toHaveBeenCalled();
});

beforeEach(() => {
  jest.clearAllMocks();
  mockGetSession.mockResolvedValue(null);
  mockSave.mockResolvedValue(undefined);
  mockClear.mockResolvedValue(undefined);
  mockClearCache.mockResolvedValue(undefined);
  mockMe.mockResolvedValue({ data: USER });
  mockLogin.mockResolvedValue(RESPONSE);
  mockRegister.mockResolvedValue(RESPONSE);
  mockLogout.mockResolvedValue(undefined);
});

it('starts unauthenticated without a stored session', async () => {
  const { result } = await renderHook(() => useAuth(), { wrapper });
  await waitFor(() => expect(result.current.state).toBe('unauthenticated'));
  expect(mockMe).not.toHaveBeenCalled();
});

it('restores and validates an existing session', async () => {
  mockGetSession.mockResolvedValue({ accessToken: 'fake-stored-token', user: USER });
  const { result } = await renderHook(() => useAuth(), { wrapper });
  await waitFor(() => expect(result.current.state).toBe('authenticated'));
  expect(result.current.user?.id).toBe(4);
  expect(mockSave).toHaveBeenCalledWith({ accessToken: 'fake-stored-token', user: USER });
});

it('clears token and cached data when session validation rejects it', async () => {
  mockGetSession.mockResolvedValue({ accessToken: 'fake-expired-token', user: USER });
  mockMe.mockRejectedValue(new ApiError({ status: 401, code: 'AUTH_INVALID_TOKEN', message: 'Sesion vencida.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false }));
  const { result } = await renderHook(() => useAuth(), { wrapper });
  await waitFor(() => expect(result.current.state).toBe('unauthenticated'));
  expect(mockClear).toHaveBeenCalledTimes(1);
  expect(mockClearCache).toHaveBeenCalledTimes(1);
});

it.each(['login', 'register'] as const)('releases the loading state after a failed %s so another attempt works', async (method) => {
  const request = method === 'login' ? mockLogin : mockRegister;
  request.mockRejectedValueOnce(new Error('Credenciales invalidas.'));
  const { result } = await renderHook(() => useAuth(), { wrapper });
  await waitFor(() => expect(result.current.state).toBe('unauthenticated'));
  const payload = { name: 'Martina', email: 'qa@example.invalid', password: 'fake-qa-password', password_confirmation: 'fake-qa-password' };
  await act(async () => { await expect(result.current[method](payload)).rejects.toThrow('Credenciales invalidas.'); });
  expect(result.current.isLoading).toBe(false);
  expect(result.current.state).toBe('unauthenticated');
  expect(mockSave).not.toHaveBeenCalled();
  await act(async () => { await result.current[method](payload); });
  expect(result.current.state).toBe('authenticated');
  expect(mockSave).toHaveBeenCalledTimes(1);
});

it('logs out locally and clears cached data even if the server is offline', async () => {
  mockGetSession.mockResolvedValue({ accessToken: 'fake-stored-token', user: USER });
  mockLogout.mockRejectedValue(new Error('Offline'));
  const { result } = await renderHook(() => useAuth(), { wrapper });
  await waitFor(() => expect(result.current.state).toBe('authenticated'));
  await act(async () => { await result.current.logout(); });
  expect(result.current.state).toBe('unauthenticated');
  expect(result.current.isLoading).toBe(false);
  expect(mockClear).toHaveBeenCalledTimes(1);
  expect(mockClearCache).toHaveBeenCalledTimes(1);
});

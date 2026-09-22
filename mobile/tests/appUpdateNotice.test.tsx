import React from 'react';
import { Alert, AppState, Linking, Platform, type AppStateStatus } from 'react-native';
import { act, render, waitFor } from '@testing-library/react-native';
import { AppUpdateNotice } from '../src/components/AppUpdateNotice';

const mockVersion = jest.fn();
let mockNativeBuild: string | null = '6';
let mockEnvironment = 'standalone';
let stateChanged: (state: AppStateStatus) => void;
const mockRemove = jest.fn();
const originalAppState = AppState.currentState;
jest.mock('../src/api/endpoints', () => ({ mobileReleaseApi: { androidVersion: () => mockVersion() } }));
jest.mock('expo-application', () => ({
  get nativeBuildVersion() { return mockNativeBuild; },
  nativeApplicationVersion: '1.0.5',
}));
jest.mock('expo-constants', () => ({ __esModule: true, default: {
  get executionEnvironment() { return mockEnvironment; },
} }));

const release = { platform: 'android', version: '1.0.6', build: 7, download_page_url: 'https://cocinacomidacontrol.com.ar/#descarga-app' };

beforeEach(() => {
  jest.clearAllMocks();
  mockNativeBuild = '6';
  mockEnvironment = 'standalone';
  jest.replaceProperty(Platform, 'OS', 'android');
  AppState.currentState = 'active';
  jest.spyOn(AppState, 'addEventListener').mockImplementation((_type, listener) => {
    stateChanged = listener;
    return { remove: mockRemove };
  });
  jest.spyOn(Alert, 'alert').mockImplementation(() => {});
  jest.spyOn(Linking, 'openURL').mockResolvedValue(undefined);
  mockVersion.mockResolvedValue({ data: release });
});
afterEach(() => { jest.restoreAllMocks(); AppState.currentState = originalAppState; });

async function showNotice() {
  const screen = await render(<AppUpdateNotice />);
  await waitFor(() => expect(Alert.alert).toHaveBeenCalledTimes(1));
  return screen;
}

it('announces both versions once and opens the page only after explicit action', async () => {
  const screen = await showNotice();
  const call = jest.mocked(Alert.alert).mock.calls[0];
  expect(call[0]).toBe('Actualización disponible');
  expect(call[1]).toContain('1.0.6');
  expect(call[1]).toContain('1.0.5');
  expect(call[2]?.find((button) => button.text === 'Más tarde')?.style).toBe('cancel');
  expect(call[3]?.cancelable).toBe(true);
  expect(Linking.openURL).not.toHaveBeenCalled();
  await screen.rerender(<AppUpdateNotice />);
  await act(() => { stateChanged('background'); stateChanged('active'); });
  expect(mockVersion).toHaveBeenCalledTimes(1);
  expect(Alert.alert).toHaveBeenCalledTimes(1);
  await act(() => { call[2]?.find((button) => button.text === 'Ir a descargar')?.onPress?.(); });
  expect(Linking.openURL).toHaveBeenCalledWith(release.download_page_url);
});

it('waits for the foreground without polling again', async () => {
  AppState.currentState = 'background';
  await render(<AppUpdateNotice />);
  expect(Alert.alert).not.toHaveBeenCalled();
  await act(() => stateChanged('active'));
  expect(Alert.alert).toHaveBeenCalledTimes(1);
  expect(mockVersion).toHaveBeenCalledTimes(1);
});

it.each([6, 5])('keeps the app uninterrupted for published build %s', async (build) => {
  mockVersion.mockResolvedValue({ data: { ...release, build } });
  await render(<AppUpdateNotice />);
  expect(Alert.alert).not.toHaveBeenCalled();
});

it('ignores a failed version request', async () => {
  mockVersion.mockRejectedValue(new Error('Offline or server unavailable'));
  await render(<AppUpdateNotice />);
  expect(Alert.alert).not.toHaveBeenCalled();
  expect(Linking.openURL).not.toHaveBeenCalled();
});

it('ignores a malformed response', async () => {
  mockVersion.mockResolvedValue({ data: null });
  await render(<AppUpdateNotice />);
  expect(Alert.alert).not.toHaveBeenCalled();
});

it('does not show a late response after unmounting', async () => {
  let resolve!: (response: unknown) => void;
  mockVersion.mockImplementation(() => new Promise((done) => { resolve = done; }));
  const screen = await render(<AppUpdateNotice />);
  await screen.unmount();
  await act(() => resolve({ data: release }));
  expect(Alert.alert).not.toHaveBeenCalled();
  expect(mockRemove).toHaveBeenCalledTimes(1);
});

it('handles a browser-opening error without rejecting the button handler', async () => {
  jest.mocked(Linking.openURL).mockRejectedValue(new Error('No browser'));
  await showNotice();
  const button = jest.mocked(Alert.alert).mock.calls[0][2]?.find((item) => item.text === 'Ir a descargar');
  await act(() => { button?.onPress?.(); });
  await waitFor(() => expect(Alert.alert).toHaveBeenCalledWith('No se pudo abrir la página', expect.stringContaining(release.download_page_url)));
});

it('does not query Android releases on iOS', async () => {
  jest.replaceProperty(Platform, 'OS', 'ios');
  await render(<AppUpdateNotice />);
  expect(mockVersion).not.toHaveBeenCalled();
});

it('does not compare Expo Go to the project APK', async () => {
  mockEnvironment = 'storeClient';
  await render(<AppUpdateNotice />);
  expect(mockVersion).not.toHaveBeenCalled();
});

it('skips the request when the installed native build is unknown', async () => {
  mockNativeBuild = null;
  await render(<AppUpdateNotice />);
  expect(mockVersion).not.toHaveBeenCalled();
});

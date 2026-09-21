import React from 'react';
import { Alert, BackHandler } from 'react-native';
import { act, fireEvent, render, waitFor } from '@testing-library/react-native';
import { GoalsScreen } from '../src/screens/GoalsScreen';

const mockReplace = jest.fn();
const mockSetParams = jest.fn();
const mockRouter = { replace: mockReplace, setParams: mockSetParams };
const mockUpdate = jest.fn();
const mockCatalog = jest.fn();
const mockRefresh = jest.fn();
const mockClearCache = jest.fn();
const mockGoBackOrHome = jest.fn();
let mockParams: { from?: string } = {};
let mockProfile = {
  height_cm: 170,
  current_weight_kg: null,
  target_weight_kg: null,
  meals_per_day: 4,
  activity_level: null,
  objectives: [{ id: 1 }],
};

jest.mock('expo-router', () => ({
  useRouter: () => mockRouter,
  useLocalSearchParams: () => mockParams,
  useFocusEffect: (cb: () => void | (() => void)) => { jest.requireActual<typeof import('react')>('react').useEffect(cb, [cb]); },
}));
jest.mock('../src/hooks/useProfile', () => ({
  useProfile: () => ({ data: mockProfile, loading: false, error: null, refresh: mockRefresh }),
}));
jest.mock('../src/api/endpoints', () => ({
  objectivesApi: { catalog: () => mockCatalog() },
  profileApi: { update: (...args: unknown[]) => mockUpdate(...args) },
}));
jest.mock('../src/storage/offlineCache', () => ({ offlineCache: { clearAll: () => mockClearCache() } }));
jest.mock('../src/utils/navigation', () => ({ goBackOrHome: () => mockGoBackOrHome() }));
jest.mock('../src/components/AppHeader', () => ({
  AppHeader: ({ onBack }: { onBack: () => void }) => jest.requireActual<typeof import('react')>('react').createElement(
    jest.requireActual<typeof import('react-native')>('react-native').Button, { title: 'Volver', onPress: onBack },
  ),
}));
jest.mock('../src/components/LoadingScreen', () => ({ LoadingScreen: 'LoadingScreen' }));
jest.mock('../src/components/ErrorState', () => ({ ErrorState: 'ErrorState' }));

describe('GoalsScreen onboarding navigation', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockParams = { from: 'onboarding' };
    mockProfile.objectives = [{ id: 1 }];
    mockCatalog.mockResolvedValue({ data: [{ id: 1, code: 'save_money', name: 'Ahorrar' }] });
    mockUpdate.mockResolvedValue({ data: {} });
    mockClearCache.mockResolvedValue(undefined);
    jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);
  });

  afterEach(() => { jest.restoreAllMocks(); });

  async function openForm() {
    const screen = await render(<GoalsScreen />);
    await waitFor(() => expect(screen.getByLabelText('Altura (cm)').props.value).toBe('170'));
    return screen;
  }

  it('saves optional weights as null and asks to continue before returning to onboarding', async () => {
    const screen = await openForm();
    expect(screen.getByLabelText('Cantidad de comidas por día').props.value).toBe('4');
    expect(screen.queryByLabelText('Cantidad habitual de personas')).toBeNull();
    await fireEvent.press(screen.getByText('Guardar'));
    await waitFor(() => expect(Alert.alert).toHaveBeenCalled());
    expect(mockUpdate).toHaveBeenCalledWith({
      height_cm: 170, current_weight_kg: null, target_weight_kg: null,
      meals_per_day: 4, activity_level: null, objective_ids: [1],
    });
    expect(mockClearCache).toHaveBeenCalledTimes(1);
    expect(mockRefresh).toHaveBeenCalledTimes(1);
    expect(Alert.alert).toHaveBeenCalledWith('Datos guardados',
      'Podés modificarlos más adelante desde tu perfil.', expect.any(Array));
    expect(mockReplace).not.toHaveBeenCalled();
    const buttons = jest.mocked(Alert.alert).mock.calls[0][2]!;
    expect(buttons.find((button) => button.text === 'Seguir editando')?.style).toBe('cancel');
    await act(() => { buttons.find((button) => button.text === 'Continuar')!.onPress!(); });
    expect(mockSetParams).toHaveBeenCalledWith({ from: undefined });
    expect(mockReplace).toHaveBeenCalledWith('/(app)/onboarding');
  });

  it('returns to onboarding from the header instead of using tab history', async () => {
    const screen = await openForm();
    await fireEvent.press(screen.getByText('Volver'));
    expect(mockReplace).toHaveBeenCalledWith('/(app)/onboarding');
    expect(mockGoBackOrHome).not.toHaveBeenCalled();
    expect(mockUpdate).not.toHaveBeenCalled();
  });

  it('returns to onboarding on Android back and removes its listener when leaving', async () => {
    const remove = jest.fn();
    const addListener = jest.spyOn(BackHandler, 'addEventListener').mockReturnValue({ remove });
    const screen = await openForm();
    const listener = addListener.mock.calls.find(([event]) => event === 'hardwareBackPress')![1];
    await act(() => { expect(listener({ type: 'hardwareBackPress', timeStamp: 0 })).toBe(true); });
    expect(mockReplace).toHaveBeenCalledWith('/(app)/onboarding');
    await screen.unmount();
    expect(remove).toHaveBeenCalledTimes(1);
  });

  it.each([undefined, 'profile'])('preserves regular profile editing with source %s', async (from) => {
    mockParams = { from };
    const screen = await openForm();
    await fireEvent.press(screen.getByText('Guardar'));
    await waitFor(() => expect(screen.getByText('Tus objetivos se guardaron correctamente.')).toBeTruthy());
    expect(Alert.alert).not.toHaveBeenCalled();
    expect(mockReplace).not.toHaveBeenCalled();
    await fireEvent.press(screen.getByText('Volver'));
    expect(mockGoBackOrHome).toHaveBeenCalledTimes(1);
  });

  it('stays in the form and does not offer to continue after a failed save', async () => {
    mockUpdate.mockRejectedValue(new Error('Network failure'));
    const screen = await openForm();
    await fireEvent.press(screen.getByText('Guardar'));
    await waitFor(() => expect(screen.getByText('No pudimos guardar tus objetivos.')).toBeTruthy());
    expect(Alert.alert).not.toHaveBeenCalled();
    expect(mockReplace).not.toHaveBeenCalled();
    expect(mockClearCache).not.toHaveBeenCalled();
  });

  it('does not save or navigate when no objective is selected', async () => {
    mockProfile.objectives = [];
    const screen = await openForm();
    await fireEvent.press(screen.getByText('Guardar'));
    expect(screen.getByText('Seleccioná un objetivo.')).toBeTruthy();
    expect(mockUpdate).not.toHaveBeenCalled();
    expect(Alert.alert).not.toHaveBeenCalled();
    expect(mockReplace).not.toHaveBeenCalled();
  });
});

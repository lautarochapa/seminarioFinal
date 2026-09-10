import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { OnboardingScreen } from '../src/screens/OnboardingScreen';

const mockPush = jest.fn();
const mockReplace = jest.fn();
const mockStatus = jest.fn();

jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: mockPush, replace: mockReplace }),
  useFocusEffect: (cb: () => void | (() => void)) => { cb(); },
}));
jest.mock('../src/components/AppHeader', () => ({ AppHeader: 'AppHeader' }));
jest.mock('../src/components/LoadingScreen', () => ({ LoadingScreen: 'LoadingScreen' }));
jest.mock('../src/components/ErrorState', () => ({ ErrorState: 'ErrorState' }));
jest.mock('../src/api/endpoints', () => ({ onboardingApi: { status: (...args: unknown[]) => mockStatus(...args) } }));

function status(overrides: Record<string, unknown> = {}) {
  return {
    data: {
      complete: false,
      next_step: 'basic_profile',
      required_steps: ['basic_profile', 'objective', 'meals_per_day', 'family_group'],
      completed_count: 0,
      steps: {
        basic_profile: { complete: false, missing: ['height_cm', 'current_weight_kg'], has_target_weight: false },
        objective: { complete: false, objectives_count: 0 },
        meals_per_day: { complete: false, value: null },
        food_preferences: { complete: true, optional: true, restrictions_count: 0, allergies_count: 0, health_conditions_count: 0 },
        family_group: { complete: false, groups_count: 0 },
      },
      ...overrides,
    },
  };
}

describe('OnboardingScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockStatus.mockResolvedValue(status());
  });

  it('shows every step with its state and navigates to the step screen', async () => {
    const screen = await render(<OnboardingScreen />);

    await waitFor(() => expect(screen.getByText('1. Datos básicos')).toBeTruthy());
    expect(screen.getByText('Paso 1 de 4')).toBeTruthy();
    expect(screen.getByText('Falta: height_cm, current_weight_kg')).toBeTruthy();
    expect(screen.getByText('Opcional')).toBeTruthy();

    fireEvent.press(screen.getByText('Ir a grupo familiar'));
    expect(mockPush).toHaveBeenCalledWith('/(app)/groups');
  });

  it('offers going home when onboarding is complete', async () => {
    mockStatus.mockResolvedValue(
      status({
        complete: true,
        next_step: null,
        completed_count: 4,
        steps: {
          basic_profile: { complete: true, missing: [], has_target_weight: true },
          objective: { complete: true, objectives_count: 2 },
          meals_per_day: { complete: true, value: 4 },
          food_preferences: { complete: true, optional: true, restrictions_count: 1, allergies_count: 0, health_conditions_count: 0 },
          family_group: { complete: true, groups_count: 1 },
        },
      }),
    );

    const screen = await render(<OnboardingScreen />);
    await waitFor(() => expect(screen.getByText('Configuración completa. Ya podés usar stock y recetas.')).toBeTruthy());
    fireEvent.press(screen.getByText('Ir al inicio'));
    expect(mockReplace).toHaveBeenCalledWith('/(app)');
  });
});

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { CookedRecipesScreen } from '../src/screens/CookedRecipesScreen';

const mockPush = jest.fn();
const mockCooked = jest.fn();

jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('expo-router', () => ({ useRouter: () => ({ push: mockPush }) }));
jest.mock('../src/utils/navigation', () => ({ goBackOrHome: jest.fn() }));
jest.mock('../src/components/AppHeader', () => ({ AppHeader: 'AppHeader' }));
jest.mock('../src/components/LoadingScreen', () => ({ LoadingScreen: 'LoadingScreen' }));
jest.mock('../src/components/EmptyState', () => ({ EmptyState: ({ message }: { message: string }) => { const { Text } = require('react-native'); return <Text>{message}</Text>; } }));
jest.mock('../src/components/ErrorState', () => ({ ErrorState: 'ErrorState' }));
jest.mock('../src/api/endpoints', () => ({ recipesApi: { cookedHistory: (...args: unknown[]) => mockCooked(...args) } }));

describe('CookedRecipesScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockCooked.mockResolvedValue({
      data: [
        { id: 7, recipe_id: 42, family_group_id: 1, servings: 4, cooked_at: '2026-09-10T12:00:00Z', stock_discounted: true, notes: null, recipe: { id: 42, name: 'Panqueques' } },
      ],
      meta: { current_page: 1, per_page: 30, total: 1, last_page: 1 },
    });
  });

  it('lists cooked recipes and opens the recipe detail', async () => {
    const screen = await render(<CookedRecipesScreen />);
    await waitFor(() => expect(screen.getByText('Panqueques')).toBeTruthy());
    expect(screen.getByText(/4 porciones/)).toBeTruthy();
    fireEvent.press(screen.getByText('Panqueques'));
    expect(mockPush).toHaveBeenCalledWith({ pathname: '/(app)/recipes/[id]', params: { id: '42' } });
  });

  it('shows an empty state when there is no history', async () => {
    mockCooked.mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 30, total: 0, last_page: 1 } });
    const screen = await render(<CookedRecipesScreen />);
    await waitFor(() => expect(screen.getByText('Todavía no cocinaste ninguna receta.')).toBeTruthy());
  });
});

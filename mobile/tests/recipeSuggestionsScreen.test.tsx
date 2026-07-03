import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import { RecipeSuggestionsScreen } from '../src/screens/RecipeSuggestionsScreen';

const mockPush = jest.fn();
const mockRefresh = jest.fn();
const mockUseRecipeSuggestions = jest.fn();

jest.mock('@expo/vector-icons', () => ({
  MaterialCommunityIcons: 'MaterialCommunityIcons',
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: mockPush }),
}));

jest.mock('../src/utils/navigation', () => ({
  goBackOrHome: jest.fn(),
}));

jest.mock('../src/auth/FamilyGroupContext', () => ({
  useFamilyGroupContext: () => ({ selectedGroup: { id: 1, name: 'Demo' } }),
}));

jest.mock('../src/components/FamilyGroupSelector', () => ({
  FamilyGroupSelector: 'FamilyGroupSelector',
}));

jest.mock('../src/components/AppHeader', () => ({
  AppHeader: 'AppHeader',
}));

jest.mock('../src/components/RecipeCard', () => ({
  RecipeCard: ({ recipe, onPress }: { recipe: { name: string }; onPress: () => void }) => {
    const { Pressable, Text } = require('react-native');
    return (
      <Pressable onPress={onPress}>
        <Text>{recipe.name}</Text>
      </Pressable>
    );
  },
}));

jest.mock('../src/hooks/useRecipeSuggestions', () => ({
  useRecipeSuggestions: (...args: unknown[]) => mockUseRecipeSuggestions(...args),
}));

beforeEach(() => {
  mockPush.mockClear();
  mockRefresh.mockClear();
  mockUseRecipeSuggestions.mockReset();
});

describe('RecipeSuggestionsScreen', () => {
  it('renders valid normalized suggestions and opens recipe detail', async () => {
    mockUseRecipeSuggestions.mockReturnValue({
      data: [{ recipe: { id: 10, name: 'Arroz con pollo' }, reason: 'official_recipe' }],
      invalidCount: 0,
      loading: false,
      error: null,
      refresh: mockRefresh,
    });

    const { getByText } = await render(<RecipeSuggestionsScreen />);
    fireEvent.press(getByText('Arroz con pollo'));

    expect(mockPush).toHaveBeenCalledWith({
      pathname: '/(app)/recipes/[id]',
      params: { id: '10' },
    });
  });

  it('shows invalid data state without crashing when all items were discarded', async () => {
    mockUseRecipeSuggestions.mockReturnValue({
      data: [],
      invalidCount: 2,
      loading: false,
      error: null,
      refresh: mockRefresh,
    });

    const { getByText } = await render(<RecipeSuggestionsScreen />);
    expect(getByText('La respuesta contiene datos invalidos. Reintentá en unos segundos.')).toBeTruthy();

    fireEvent.press(getByText('Reintentar'));
    expect(mockRefresh).toHaveBeenCalledTimes(1);
  });
});

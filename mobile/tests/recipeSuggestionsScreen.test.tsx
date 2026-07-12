import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { RecipeSuggestionsScreen } from '../src/screens/RecipeSuggestionsScreen';
const mockPush = jest.fn(); const mockAvailable = jest.fn(); const mockAlmost = jest.fn();
jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('expo-router', () => ({ useRouter: () => ({ push: mockPush }), useFocusEffect: (callback: () => void) => require('react').useEffect(callback, []) }));
jest.mock('../src/utils/navigation', () => ({ goBackOrHome: jest.fn() }));
jest.mock('../src/auth/FamilyGroupContext', () => ({ useFamilyGroupContext: () => ({ selectedGroup: { id: 1, name: 'Demo' } }) }));
jest.mock('../src/components/FamilyGroupSelector', () => ({ FamilyGroupSelector: 'FamilyGroupSelector' }));
jest.mock('../src/components/AppHeader', () => ({ AppHeader: 'AppHeader' }));
jest.mock('../src/components/RecipeCard', () => ({ RecipeCard: ({ recipe, badge, onPress }: { recipe: { name: string }; badge: string; onPress: () => void }) => { const { Pressable, Text } = require('react-native'); return <Pressable onPress={onPress}><Text>{recipe.name}</Text><Text>{badge}</Text></Pressable>; } }));
jest.mock('../src/api/endpoints', () => ({ recipeSuggestionsApi: { available: (...args: unknown[]) => mockAvailable(...args), almostAvailable: (...args: unknown[]) => mockAlmost(...args) } }));
describe('RecipeSuggestionsScreen', () => {
  beforeEach(() => { jest.clearAllMocks(); mockAvailable.mockResolvedValue({ data: [{ recipe: { id: 10, name: 'Arroz con pollo' } }] }); mockAlmost.mockResolvedValue({ data: [{ recipe: { id: 11, name: 'Tarta' }, missing_ingredients_count: 2 }] }); });
  it('shows separated sections and opens recipe', async () => { const screen = await render(<RecipeSuggestionsScreen />); await waitFor(() => expect(screen.getByText('Arroz con pollo')).toBeTruthy()); expect(screen.getByText('Para cocinar ahora')).toBeTruthy(); expect(screen.getByText('Te falta poco')).toBeTruthy(); expect(screen.getByText('Te faltan 2 ingredientes')).toBeTruthy(); fireEvent.press(screen.getByText('Arroz con pollo')); expect(mockPush).toHaveBeenCalledWith({ pathname: '/(app)/recipes/[id]', params: { id: '10' } }); });
  it('respects the active group', async () => { await render(<RecipeSuggestionsScreen />); await waitFor(() => expect(mockAvailable).toHaveBeenCalledWith(1)); expect(mockAlmost).toHaveBeenCalledWith(1); });
});

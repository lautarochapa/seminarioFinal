import React from 'react';
import { render, waitFor } from '@testing-library/react-native';
import { ShoppingListsScreen } from '../src/screens/ShoppingListsScreen';

const mockList = jest.fn();
let mockFocused = true;
let mockGroup: { id: number; name: string } | null;
jest.mock('../src/api/endpoints', () => ({ shoppingListsApi: { list: (...args: unknown[]) => mockList(...args) } }));
jest.mock('../src/auth/FamilyGroupContext', () => ({ useFamilyGroupContext: () => ({ selectedGroup: mockGroup }) }));
jest.mock('../src/components/FamilyGroupSelector', () => ({ FamilyGroupSelector: 'FamilyGroupSelector' }));
jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('react-native-safe-area-context', () => ({ useSafeAreaInsets: () => ({ top: 0, bottom: 0, left: 0, right: 0 }) }));
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn() }),
  useFocusEffect: (cb: () => void | (() => void)) => {
    jest.requireActual<typeof import('react')>('react').useEffect(() => {
      if (mockFocused) return cb();
    }, [cb, mockFocused]);
  },
}));

const response = (status: string) => ({
  data: [{ id: 5, status, source_type: 'manual' }],
  meta: { current_page: 1, last_page: 1, total: 1, per_page: 20 },
});

beforeEach(() => {
  jest.clearAllMocks();
  mockFocused = true;
  mockGroup = { id: 7, name: 'Hogar QA' };
  mockList.mockResolvedValue(response('active'));
});

it('loads the first page only once on entry', async () => {
  const screen = await render(<ShoppingListsScreen />);
  await waitFor(() => expect(screen.getByText('Lista para comprar')).toBeTruthy());
  expect(mockList).toHaveBeenCalledTimes(1);
});

it('shows completed after returning from the purchase without pulling to refresh', async () => {
  const screen = await render(<ShoppingListsScreen />);
  await waitFor(() => expect(screen.getByText('Lista para comprar')).toBeTruthy());
  mockFocused = false;
  await screen.rerender(<ShoppingListsScreen />);
  mockList.mockResolvedValue(response('completed'));
  mockFocused = true;
  await screen.rerender(<ShoppingListsScreen />);
  await waitFor(() => expect(screen.getByText('Completada')).toBeTruthy());
  expect(screen.queryByText('Lista para comprar')).toBeNull();
  expect(mockList).toHaveBeenLastCalledWith(7, { page: 1, per_page: 20 });
  expect(mockList).toHaveBeenCalledTimes(2);
});

it('does not query lists without a selected household', async () => {
  mockGroup = null;
  const screen = await render(<ShoppingListsScreen />);
  mockFocused = false;
  await screen.rerender(<ShoppingListsScreen />);
  mockFocused = true;
  await screen.rerender(<ShoppingListsScreen />);
  expect(mockList).not.toHaveBeenCalled();
});

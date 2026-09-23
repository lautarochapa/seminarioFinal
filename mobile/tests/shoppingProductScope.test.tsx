import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { ShoppingListDetailScreen } from '../src/screens/ShoppingListDetailScreen';

const mockProducts = jest.fn();
const mockCreateItem = jest.fn();
const mockRefresh = jest.fn();
jest.mock('../src/api/endpoints', () => ({
  productsApi: { list: (...args: unknown[]) => mockProducts(...args) },
  shoppingListItemsApi: { create: (...args: unknown[]) => mockCreateItem(...args) },
  shoppingListsApi: {},
}));
jest.mock('../src/auth/FamilyGroupContext', () => ({ useFamilyGroupContext: () => ({ selectedGroup: { id: 7, name: 'Hogar QA' } }) }));
jest.mock('../src/hooks/useShoppingListDetail', () => ({
  useShoppingListDetail: () => ({ list: { id: 5, status: 'active' }, items: [], loading: false, error: null, refresh: mockRefresh }),
}));
jest.mock('../src/hooks/useUnits', () => ({ useUnits: () => ({ data: [] }) }));
jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('react-native-safe-area-context', () => ({ useSafeAreaInsets: () => ({ top: 0, bottom: 0, left: 0, right: 0 }) }));
jest.mock('expo-router', () => ({ useRouter: () => ({ push: jest.fn(), replace: jest.fn() }), useFocusEffect: jest.fn() }));

beforeEach(() => {
  jest.clearAllMocks();
  mockCreateItem.mockResolvedValue({ data: { id: 10 } });
  mockProducts.mockImplementation(async (filters) => ({
    data: filters.family_group_id === 7 ? [{ id: 12, name: 'QA arroz', unit: { id: 4, symbol: 'kg' } }] : [],
    meta: { current_page: 1, last_page: 1, total: filters.family_group_id === 7 ? 1 : 0, per_page: 20 },
  }));
});

it('includes the active household in the initial catalog request', async () => {
  const screen = await render(<ShoppingListDetailScreen listId={5} />);
  await fireEvent.press(screen.getByLabelText('Agregar item'));
  await waitFor(() => expect(screen.getByText('QA arroz')).toBeTruthy());
  expect(mockProducts).toHaveBeenCalledWith({ family_group_id: 7 });
});

it('keeps the household scope while searching and adds the existing product, not free text', async () => {
  const screen = await render(<ShoppingListDetailScreen listId={5} />);
  await fireEvent.press(screen.getByLabelText('Agregar item'));
  await fireEvent.changeText(screen.getByPlaceholderText(/necesit.*comprar/), 'QA arroz');
  await waitFor(() => expect(mockProducts).toHaveBeenLastCalledWith({ family_group_id: 7, search: 'QA arroz', page: 1 }));
  await fireEvent.press(screen.getByText('QA arroz'));
  await fireEvent.press(screen.getByText('Agregar'));
  await waitFor(() => expect(mockCreateItem).toHaveBeenCalledWith(7, 5, { product_id: 12, free_text_name: undefined, quantity: 1, unit_id: 4 }));
  expect(mockCreateItem).toHaveBeenCalledTimes(1);
});
